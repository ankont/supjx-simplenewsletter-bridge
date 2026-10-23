<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/** Resolve email resources against the public site, without reserialising HTML. */
final class EmailUrls
{
    public static function absolute(string $url, string $root): string
    {
        $url = trim($url);
        // Preserve schemes (including cid/data/mailto), anchors and placeholders.
        if ($url === '' || $url[0] === '#' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) || str_contains($url, '{')) { return $url; }
        $base = parse_url($root);
        if (!$base || !isset($base['scheme'], $base['host']) || !in_array($base['scheme'], ['http', 'https'], true)) {
            throw new \RuntimeException('Invalid public site URL');
        }
        if (str_starts_with($url, '//')) { return $base['scheme'] . ':' . $url; }
        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        $parts = preg_split('~([?#].*)~s', $url, 2, PREG_SPLIT_DELIM_CAPTURE);
        $path = $parts[0];
        $path = str_starts_with($path, '/') ? $path : rtrim($base['path'] ?? '/', '/') . '/' . $path;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') { array_pop($segments); }
            elseif ($segment !== '.' && $segment !== '') { $segments[] = $segment; }
        }
        return $origin . '/' . implode('/', $segments) . (str_ends_with($path, '/') && $segments ? '/' : '') . ($parts[1] ?? '');
    }

    public static function html(string $html, ?string $root = null): string
    {
        $root ??= Uri::root();
        $resolve = static fn ($url) => self::absolute($url, $root);
        // Keep conditional comments, markup, text and quote styles intact.
        $html = preg_replace_callback('~<!--[\s\S]*?-->|<[^>]+>~', static function ($tag) use ($resolve) {
            if (str_starts_with($tag[0], '<!--')) { return $tag[0]; }
            $markup = preg_replace_callback('~(\ssrcset\s*=\s*)(["\'])(.*?)\2~is', static function ($a) use ($resolve) {
                // Data URLs contain commas; leave such sets intact rather than corrupting them.
                if (stripos($a[3], 'data:') !== false) { return $a[0]; }
                $set = preg_replace_callback('~(^|,\s*)([^\s,]+)~', static function ($candidate) use ($resolve) {
                    return $candidate[1] . htmlspecialchars($resolve(html_entity_decode($candidate[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }, $a[3]);
                return $a[1] . $a[2] . $set . $a[2];
            }, $tag[0]);
            return preg_replace_callback('~(\s(?:src|href|background|poster)\s*=\s*)(["\'])(.*?)\2|(\s(?:src|href|background|poster)\s*=\s*)([^\s>"\'`=]+)~is', static function ($a) use ($resolve) {
                $quoted = $a[1] !== '';
                $value = html_entity_decode($quoted ? $a[3] : $a[5], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $value = htmlspecialchars($resolve($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return $quoted ? $a[1] . $a[2] . $value . $a[2] : $a[4] . '"' . $value . '"';
            }, $markup);
        }, $html);
        // Background images in inline styles and style blocks.
        return preg_replace_callback('~url\(\s*(["\']?)([^)"\']+)\1\s*\)~i', static function ($m) use ($resolve) {
            $url = $resolve(html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return 'url(' . $m[1] . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[1] . ')';
        }, $html);
    }
}
