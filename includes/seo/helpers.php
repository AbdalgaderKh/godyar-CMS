<?php
declare(strict_types=1);

/**
 * SEO + Performance helpers (Godyar CMS)
 * - Canonical URL cleaning
 * - JSON-LD builders
 * - FAQ extractor
 * - Image lazy-loading optimizer
 */

if (!function_exists('gdy_base_origin')) {
    function gdy_base_origin(): string
    {
        // Prefer configured BASE_URL / site.url, otherwise infer from request
        $base = '';
        if (defined('BASE_URL')) {
            $base = (string)BASE_URL;
        } elseif (defined('GODYAR_BASE_URL')) {
            $base = (string)GODYAR_BASE_URL;
        }

        // Try settings (legacy globals)
        if ($base === '' && isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
            $base = (string)($GLOBALS['site_settings']['site.url'] ?? $GLOBALS['site_settings']['site_url'] ?? '');
        }

        if ($base !== '') {
            $parts = @parse_url($base) ?: [];
            $scheme = (string)($parts['scheme'] ?? '');
            $host   = (string)($parts['host'] ?? '');
            $port   = (string)($parts['port'] ?? '');
            if ($scheme && $host) {
                return $scheme . '://' . $host . ($port ? (':' . $port) : '');
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}

if (!function_exists('gdy_base_url')) {
    function gdy_base_url(): string
    {
        $origin = gdy_base_origin();
        $path = '';

        $base = '';
        if (defined('BASE_URL')) {
            $base = (string)BASE_URL;
        } elseif (defined('GODYAR_BASE_URL')) {
            $base = (string)GODYAR_BASE_URL;
        }

        if ($base === '' && isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
            $base = (string)($GLOBALS['site_settings']['site.url'] ?? $GLOBALS['site_settings']['site_url'] ?? '');
        }

        if ($base !== '') {
            $parts = @parse_url($base) ?: [];
            $p = (string)($parts['path'] ?? '');
            if ($p !== '' && $p !== '/') {
                $path = rtrim($p, '/');
            }
        }

        return rtrim($origin . $path, '/');
    }
}

if (!function_exists('gdy_current_url')) {
    function gdy_current_url(): string
    {
        $origin = gdy_base_origin();
        $uri = (string)($_SERVER['GDY_ORIGINAL_REQUEST_URI'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($uri === '') $uri = '/';
        return $origin . $uri;
    }
}

if (!function_exists('gdy_clean_url')) {
    function gdy_clean_url(string $url): string
    {
        $parts = @parse_url($url);
        if (!$parts || !is_array($parts)) return $url;

        $scheme = (string)($parts['scheme'] ?? '');
        $host   = (string)($parts['host'] ?? '');
        $port   = isset($parts['port']) ? (int)$parts['port'] : null;
        $path   = (string)($parts['path'] ?? '/');
        $query  = (string)($parts['query'] ?? '');

        // remove tracking params
        $drop = [
            'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
            'gclid','fbclid','yclid','mc_cid','mc_eid','igshid','ref','ref_src'
        ];
        $q = [];
        if ($query !== '') {
            parse_str($query, $q);
            foreach ($drop as $k) {
                if (array_key_exists($k, $q)) unset($q[$k]);
            }
            foreach ($q as $k => $v) {
                if ($v === '' || $v === null) unset($q[$k]);
            }
        }
        $qs = $q ? http_build_query($q) : '';

        $out = '';
        if ($scheme && $host) {
            $out = $scheme . '://' . $host . ($port ? (':' . $port) : '') . $path;
        } else {
            $out = $path;
        }
        if ($qs !== '') $out .= '?' . $qs;
        return $out;
    }
}

if (!function_exists('gdy_optimize_html_images')) {
    function gdy_optimize_html_images(string $html): string
    {
        if ($html === '' || stripos($html, '<img') === false) return $html;

        $html = preg_replace_callback('/<img\b[^>]*>/i', function($m) {
            $tag = $m[0];

            if (stripos($tag, 'loading=') === false) {
                $tag = rtrim(substr($tag, 0, -1)) . ' loading="lazy">';
            }
            if (stripos($tag, 'decoding=') === false) {
                $tag = rtrim(substr($tag, 0, -1)) . ' decoding="async">';
            }
            if (stripos($tag, 'referrerpolicy=') === false) {
                $tag = rtrim(substr($tag, 0, -1)) . ' referrerpolicy="no-referrer-when-downgrade">';
            }
            return $tag;
        }, $html);

        return $html;
    }
}

if (!function_exists('gdy_extract_faq')) {
    function gdy_extract_faq(string $html, int $max = 10): array
    {
        $text = trim(strip_tags($html));
        if ($text === '') return [];

        $lines = preg_split('/\R+/', $text);
        $faqs = [];
        $q = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^(س\s*[:\-]|سؤال\s*[:\-])\s*(.+)$/u', $line, $m)) {
                $q = trim($m[2]);
                continue;
            }
            if ($q !== null && preg_match('/^(ج\s*[:\-]|جواب\s*[:\-])\s*(.+)$/u', $line, $m)) {
                $a = trim($m[2]);
                if ($q !== '' && $a !== '') {
                    $faqs[] = ['question' => $q, 'answer' => $a];
                    if (count($faqs) >= $max) break;
                }
                $q = null;
                continue;
            }

            if (preg_match('/^Q\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $q = trim($m[1]);
                continue;
            }
            if ($q !== null && preg_match('/^A\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $a = trim($m[1]);
                if ($q !== '' && $a !== '') {
                    $faqs[] = ['question' => $q, 'answer' => $a];
                    if (count($faqs) >= $max) break;
                }
                $q = null;
                continue;
            }
        }

        return $faqs;
    }
}

if (!function_exists('gdy_jsonld')) {
    function gdy_jsonld($data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('gdy_schema_org')) {
    function gdy_schema_org(array $siteSettings): array
    {
        $base = gdy_base_url();
        $name = (string)($siteSettings['site_name'] ?? $siteSettings['site.name'] ?? 'Godyar News');
        $logo = trim((string)($siteSettings['site_logo'] ?? $siteSettings['site.logo'] ?? ''));
        $sameAs = [];

        foreach (['social_facebook','social_twitter','social_youtube','social_telegram','social_instagram'] as $k) {
            $v = trim((string)($siteSettings[$k] ?? ''));
            if ($v !== '') $sameAs[] = $v;
        }

        $out = [
            '@type' => 'Organization',
            '@id'   => $base . '/#organization',
            'name'  => $name,
            'url'   => $base,
        ];

        if ($logo !== '') {
            $out['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo,
            ];
        }

        if (!empty($sameAs)) {
            $out['sameAs'] = $sameAs;
        }

        return $out;
    }
}

if (!function_exists('gdy_schema_website')) {
    function gdy_schema_website(array $siteSettings): array
    {
        $base = gdy_base_url();
        $name = (string)($siteSettings['site_name'] ?? $siteSettings['site.name'] ?? 'Godyar News');

        $searchPath = '/search';
        if (is_file(ROOT_PATH . '/search.php')) {
            $searchPath = '/search.php';
        } elseif (is_file(ROOT_PATH . '/frontend/search.php')) {
            $searchPath = '/frontend/search.php';
        } elseif (is_file(ROOT_PATH . '/v16/frontend/search.php')) {
            $searchPath = '/v16/frontend/search.php';
        }

        return [
            '@type' => 'WebSite',
            '@id'   => $base . '/#website',
            'url'   => $base . '/',
            'name'  => $name,
            'publisher' => ['@id' => $base . '/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $base . $searchPath . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}

if (!function_exists('gdy_schema_webpage')) {
    function gdy_schema_webpage(string $title, string $url, string $desc): array
    {
        $base = gdy_base_url();
        return [
            '@type' => 'WebPage',
            '@id'   => $url . '#webpage',
            'url'   => $url,
            'name'  => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'description' => html_entity_decode($desc, ENT_QUOTES, 'UTF-8'),
            'isPartOf' => ['@id' => $base . '/#website'],
        ];
    }
}

if (!function_exists('gdy_schema_breadcrumb')) {
    function gdy_schema_breadcrumb(array $items, string $pageUrl): array
    {
        $pos = 1;
        $list = [];
        foreach ($items as $it) {
            $name = trim((string)($it['name'] ?? ''));
            $url  = trim((string)($it['url'] ?? ''));
            if ($name === '' || $url === '') continue;
            $list[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $name,
                'item' => $url,
            ];
        }
        return [
            '@type' => 'BreadcrumbList',
            '@id'   => $pageUrl . '#breadcrumb',
            'itemListElement' => $list,
        ];
    }
}

if (!function_exists('gdy_schema_newsarticle')) {
    function gdy_schema_newsarticle(array $pageSeo, array $siteSettings): array
    {
        $base = gdy_base_url();
        $url  = (string)($pageSeo['url'] ?? '');
        $title = (string)($pageSeo['title'] ?? '');
        $desc  = (string)($pageSeo['description'] ?? '');
        $img   = (string)($pageSeo['image'] ?? '');
        $published = (string)($pageSeo['published_time'] ?? '');
        $modified  = (string)($pageSeo['modified_time'] ?? '');
        $author    = (string)($pageSeo['author'] ?? '');
        $section   = (string)($pageSeo['article_section'] ?? '');

        $out = [
            '@type' => 'NewsArticle',
            '@id'   => $url . '#newsarticle',
            'mainEntityOfPage' => $url,
            'headline' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'description' => html_entity_decode($desc, ENT_QUOTES, 'UTF-8'),
            'datePublished' => $published,
            'dateModified'  => $modified !== '' ? $modified : $published,
            'publisher' => ['@id' => $base . '/#organization'],
        ];

        if ($img !== '') {
            $out['image'] = [$img];
        }
        if ($author !== '') {
            $out['author'] = [
                '@type' => 'Person',
                'name' => $author,
            ];
        }
        if ($section !== '') {
            $out['articleSection'] = $section;
        }

        return $out;
    }
}

if (!function_exists('gdy_schema_video')) {
    function gdy_schema_video(array $pageSeo): array
    {
        $url = (string)($pageSeo['url'] ?? '');
        $v   = trim((string)($pageSeo['video_url'] ?? ''));
        if ($v === '' || $url === '') return [];

        $title = (string)($pageSeo['title'] ?? '');
        $desc  = (string)($pageSeo['description'] ?? '');
        $thumb = (string)($pageSeo['image'] ?? '');

        $out = [
            '@type' => 'VideoObject',
            '@id'   => $url . '#video',
            'name'  => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'description' => html_entity_decode($desc, ENT_QUOTES, 'UTF-8'),
            'contentUrl' => $v,
            'embedUrl'   => $v,
        ];
        if ($thumb !== '') $out['thumbnailUrl'] = [$thumb];
        return $out;
    }
}

if (!function_exists('gdy_schema_faq')) {
    function gdy_schema_faq(array $faqs, string $pageUrl): array
    {
        $main = [];
        foreach ($faqs as $f) {
            $q = trim((string)($f['question'] ?? ''));
            $a = trim((string)($f['answer'] ?? ''));
            if ($q === '' || $a === '') continue;
            $main[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }
        return [
            '@type' => 'FAQPage',
            '@id'   => $pageUrl . '#faq',
            'mainEntity' => $main,
        ];
    }
}


// -----------------------------------------------------------------------------
// hreflang helpers (per-page)
// -----------------------------------------------------------------------------
if (!function_exists('gdy_hreflang_map')) {
    /**
     * Build hreflang URLs for the SAME page by switching ?lang=xx
     * - Keeps existing non-tracking query params (e.g., id, page)
     * - Drops tracking params via gdy_clean_url()
     * - Default language (ar) has no lang param (canonical-friendly)
     *
     * @return array<string,string> lang => absolute_url
     */
    function gdy_hreflang_map(string $url = '', ?array $langs = null, string $defaultLang = 'ar'): array
    {
        $langs = $langs && is_array($langs) ? $langs : ((isset($GLOBALS['SUPPORTED_LANGS']) && is_array($GLOBALS['SUPPORTED_LANGS'])) ? $GLOBALS['SUPPORTED_LANGS'] : [$defaultLang]);

        $url = $url !== '' ? $url : (function_exists('gdy_current_url') ? gdy_current_url() : '');
        if ($url === '') return [];

        // Clean tracking params first
        if (function_exists('gdy_clean_url')) {
            $url = gdy_clean_url($url);
        }

        $parts = @parse_url($url);
        if (!$parts || !is_array($parts)) return [];

        $scheme = (string)($parts['scheme'] ?? '');
        $host   = (string)($parts['host'] ?? '');
        $port   = isset($parts['port']) ? (int)$parts['port'] : null;
        $path   = (string)($parts['path'] ?? '/');
        $query  = (string)($parts['query'] ?? '');

        parse_str($query, $q);
        if (!is_array($q)) $q = [];

        $origin = '';
        if ($scheme && $host) {
            $origin = $scheme . '://' . $host . ($port ? (':' . $port) : '');
        } else {
            $origin = function_exists('gdy_base_origin') ? gdy_base_origin() : '';
        }

        $out = [];
        foreach ($langs as $lg) {
            $lg = strtolower(trim((string)$lg));
            if ($lg === '') continue;

            $qq = $q;

            if ($lg === $defaultLang) {
                // canonical-friendly: remove lang param for default
                unset($qq['lang']);
            } else {
                $qq['lang'] = $lg;
            }

            $qs = $qq ? http_build_query($qq) : '';
            $u = $origin . $path . ($qs !== '' ? ('?' . $qs) : '');
            $out[$lg] = $u;
        }

        // x-default points to default language
        $out['x-default'] = $out[$defaultLang] ?? ($origin . $path);
        return $out;
    }
}

if (!function_exists('gdy_current_url_clean')) {
    function gdy_current_url_clean(): string
    {
        $u = function_exists('gdy_current_url') ? gdy_current_url() : '';
        return ($u !== '' && function_exists('gdy_clean_url')) ? gdy_clean_url($u) : $u;
    }
}
