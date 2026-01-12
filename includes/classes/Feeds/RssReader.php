<?php
declare(strict_types=1);

namespace Godyar\Feeds;

/**
 * RssReader — safe RSS/Atom reader.
 * - Reads only feed-provided fields (title/summary/link/image/date).
 * - Does NOT scrape full articles from source websites.
 */
final class RssReader
{
    /**
     * @return array<int, array{title:string, link:string, date:string, summary:string, image:string}>
     */
    public static function fetch(string $url, int $limit = 10): array
    {
        $url = trim($url);
        if ($url === '') return [];

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout'    => 10,
                    'user_agent' => 'GodyarRssReader/1.0 (+https://godyar.org)',
                ],
            ]);

            $xmlString = @file_get_contents($url, false, $context);
            if ($xmlString === false || trim($xmlString) === '') return [];

            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) return [];

            $items = [];

            // RSS 2.0
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $title = self::clean((string)($item->title ?? ''));
                    $link  = self::clean((string)($item->link ?? ''));
                    $date  = self::clean((string)($item->pubDate ?? ''));
                    $summary = self::extractRssSummary($item);
                    $image   = self::extractRssImage($item);

                    if ($title === '' || $link === '') continue;

                    $items[] = [
                        'title'   => $title,
                        'link'    => $link,
                        'date'    => $date,
                        'summary' => $summary,
                        'image'   => $image,
                    ];
                    if (count($items) >= $limit) break;
                }
                return $items;
            }

            // Atom
            if (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $title = self::clean((string)($entry->title ?? ''));

                    $link = '';
                    if (isset($entry->link)) {
                        foreach ($entry->link as $lnk) {
                            $href = (string)($lnk['href'] ?? '');
                            $rel  = (string)($lnk['rel'] ?? '');
                            if ($href !== '' && ($rel === '' || $rel === 'alternate')) { $link = $href; break; }
                            if ($href !== '' && $link === '') $link = $href;
                        }
                    }

                    $date = self::clean((string)($entry->updated ?? $entry->published ?? ''));
                    $summary = self::clean(self::stripHtml((string)($entry->summary ?? '')));
                    if ($summary === '') {
                        $summary = self::clean(self::stripHtml((string)($entry->content ?? '')));
                    }

                    $image = self::extractAtomImage($entry);

                    if ($title === '' || $link === '') continue;

                    $items[] = [
                        'title'   => $title,
                        'link'    => $link,
                        'date'    => $date,
                        'summary' => self::limitLen($summary, 1000),
                        'image'   => $image,
                    ];
                    if (count($items) >= $limit) break;
                }
                return $items;
            }

            return [];
        } catch (\Throwable $e) {
            @error_log('[RssReader] ' . $e->getMessage());
            return [];
        }
    }

    private static function extractRssSummary(\SimpleXMLElement $item): string
    {
        $summary = (string)($item->description ?? '');
        // content:encoded
        $nsContent = $item->children('http://purl.org/rss/1.0/modules/content/');
        if ($summary === '' && isset($nsContent->encoded)) {
            $summary = (string)$nsContent->encoded;
        }
        $summary = self::clean(self::stripHtml($summary));
        return self::limitLen($summary, 1000);
    }

    private static function extractRssImage(\SimpleXMLElement $item): string
    {
        // enclosure url
        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enc) {
                $u = (string)($enc['url'] ?? '');
                if ($u !== '') return self::clean($u);
            }
        }
        // media:thumbnail / media:content
        $media = $item->children('http://search.yahoo.com/mrss/');
        if ($media) {
            if (isset($media->thumbnail)) {
                foreach ($media->thumbnail as $t) {
                    $u = (string)($t['url'] ?? '');
                    if ($u !== '') return self::clean($u);
                }
            }
            if (isset($media->content)) {
                foreach ($media->content as $c) {
                    $u = (string)($c['url'] ?? '');
                    if ($u !== '') return self::clean($u);
                }
            }
        }
        return '';
    }

    private static function extractAtomImage(\SimpleXMLElement $entry): string
    {
        // media namespace in Atom
        $media = $entry->children('http://search.yahoo.com/mrss/');
        if ($media) {
            if (isset($media->thumbnail)) {
                foreach ($media->thumbnail as $t) {
                    $u = (string)($t['url'] ?? '');
                    if ($u !== '') return self::clean($u);
                }
            }
            if (isset($media->content)) {
                foreach ($media->content as $c) {
                    $u = (string)($c['url'] ?? '');
                    if ($u !== '') return self::clean($u);
                }
            }
        }
        return '';
    }

    private static function stripHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', strip_tags($html)));
    }

    private static function clean(string $s): string
    {
        $s = trim($s);
        $s = preg_replace("/\r\n|\r|\n/u", " ", $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private static function limitLen(string $s, int $max): string
    {
        if (mb_strlen($s, 'UTF-8') <= $max) return $s;
        return mb_substr($s, 0, $max, 'UTF-8') . '…';
    }
}
