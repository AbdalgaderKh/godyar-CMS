<?php
declare(strict_types=1);

/**
 * كلاس بسيط لتوليد وسوم الـ SEO
 */

class SEO
{
    public static function meta(string $title, string $description = '', string $keywords = ''): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $k = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');

        $out  = '<title>' . $t . '</title>' . "\n";
        if ($d !== '') {
            $out .= '<meta name="description" content="' . $d . '">' . "\n";
        }
        if ($k !== '') {
            $out .= '<meta name="keywords" content="' . $k . '">' . "\n";
        }

        return $out;
    }
}
