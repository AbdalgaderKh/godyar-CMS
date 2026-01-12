<?php
/**
 * Plugin Name: Side TOC Only
 * Description: Removes duplicated Table of Contents from the post content (keeps the sidebar/widget version).
 * Version: 1.0.0
 * Author: ChatGPT
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Remove TOC containers from the_content only.
 * This keeps TOC widgets/sidebars intact because it only touches the post content HTML.
 */
function stoc_remove_toc_from_content($content) {
    if (is_admin()) { return $content; }
    if (!is_string($content) || trim($content) === '') { return $content; }

    // Run only on singular posts/pages (you can remove this check if you want it everywhere)
    if (function_exists('is_singular') && !is_singular()) { return $content; }

    // Quick check to avoid DOM parsing when not needed
    $needles = array('ez-toc-container', 'lwptoc', 'toc_container', 'toc-container', 'rank-math-toc', 'wp-block-lwptoc');
    $found = false;
    foreach ($needles as $n) {
        if (stripos($content, $n) !== false) { $found = true; break; }
    }
    if (!$found) { return $content; }

    // Use DOMDocument to remove nodes by class/id safely
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    // Wrap to reliably extract inner HTML later
    $html = '<div id="stoc-wrapper">' . $content . '</div>';

    // Ensure proper encoding for Arabic/UTF-8 content
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

    // Load without adding extra <html lang="ar" dir="rtl"><body> in output (flags depend on libxml version)
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    // XPath helpers
    $classMatch = function($class) {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    };

    // Selectors to remove (classes/ids commonly used by TOC plugins)
    $queries = array(
        "//*[@id='toc_container']",
        "//*[{$classMatch('ez-toc-container')}]",
        "//*[{$classMatch('lwptoc')}]",
        "//*[{$classMatch('wp-block-lwptoc')}]",
        "//*[{$classMatch('toc-container')}]",
        "//*[{$classMatch('rank-math-toc')}]",
    );

    foreach ($queries as $q) {
        $nodes = $xpath->query($q);
        if ($nodes && $nodes->length) {
            // Remove from the bottom to avoid live NodeList issues
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    // Extract inner HTML of wrapper
    $wrapper = $dom->getElementById('stoc-wrapper');
    if (!$wrapper) { return $content; }

    $out = '';
    foreach ($wrapper->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    libxml_clear_errors();
    return $out;
}
add_filter('the_content', 'stoc_remove_toc_from_content', 999);

/**
 * Optional: add a CSS fallback to hide TOC blocks inside the post content if any slip through.
 */
function stoc_inline_css_fallback() {
    if (is_admin()) { return; }
    if (function_exists('is_singular') && !is_singular()) { return; }

    $css = "
    .entry-content .ez-toc-container,
    .entry-content .lwptoc,
    .entry-content #toc_container,
    .entry-content .toc-container,
    .entry-content .rank-math-toc,
    .entry-content .wp-block-lwptoc { display:none !important; }
    ";

    // Attach to a common stylesheet handle if possible; otherwise print in head.
    if (function_exists('wp_add_inline_style')) {
        // Try common theme handle; if it doesn't exist, we'll print in wp_head.
        $handle = 'wp-block-library';
        wp_add_inline_style($handle, $css);
    } else {
        echo '<style>' . $css . '</style>';
    }
}
add_action('wp_enqueue_scripts', 'stoc_inline_css_fallback', 999);
