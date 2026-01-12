<?php
declare(strict_types=1);

/**
 * كلاس بسيط لقراءة RSS/Atom
 */

class FeedParser
{
    public static function parse(string $url, int $limit = 10): array
    {
        $url = trim($url);
        if ($url === '') {
            return [];
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout'    => 5,
                    'user_agent' => 'GodyarFeedParser/1.0',
                ],
            ]);

            $xmlString = @file_get_contents($url, false, $context);
            if ($xmlString === false) {
                return [];
            }

            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                return [];
            }

            $items = [];

            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $items[] = [
                        'title' => (string)($item->title ?? ''),
                        'link'  => (string)($item->link ?? ''),
                        'date'  => (string)($item->pubDate ?? ''),
                    ];
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            } elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $link = '';
                    if (isset($entry->link['href'])) {
                        $link = (string)$entry->link['href'];
                    }
                    $items[] = [
                        'title' => (string)($entry->title ?? ''),
                        'link'  => $link,
                        'date'  => (string)($entry->updated ?? $entry->published ?? ''),
                    ];
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            }

            return $items;
        } catch (\Throwable $e) {
            @error_log('[FeedParser] ' . $e->getMessage());
            return [];
        }
    }
}
