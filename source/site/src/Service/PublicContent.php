<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

/** Public copies never use subscriber data. Strict allowlist, independent of author text filters. */
final class PublicContent
{
    private const TAGS = ['p','div','span','br','hr','strong','b','em','i','u','s','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','table','thead','tbody','tfoot','tr','td','th','caption','a','img'];
    private const DROP = ['script','style','iframe','object','embed','form','input','button','textarea','select','svg','math','video','audio','source','link','meta','base','noscript','template'];
    public static function text(string $value, string $subscriber = 'Subscriber'): string
    {
        return trim(strip_tags(self::redact($value, $subscriber)));
    }
    private static function redact(string $value, string $subscriber): string
    {
        $value = preg_replace('/\{name\}/i', $subscriber, $value);
        $value = preg_replace('/\{(?:email|token|unsubscribe_link|confirm_link)\}/i', '', $value);
        $value = preg_replace('/[A-Z0-9.!#$%&\x27*+\/=?^_\x60{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[email]', $value);
        return preg_replace('/\{[a-z_]+\}/i', '', $value);
    }
    private static function url(string $url, bool $image): string
    {
        $url = trim($url);
        $decoded = $url;
        for ($i = 0; $i < 3; $i++) { $decoded = rawurldecode($decoded); }
        if ($url === '' || preg_match('/[\x00-\x20\x7f{}?#@]/', $decoded) || str_contains($decoded, chr(92))
            || str_starts_with($decoded, '//') || preg_match('/(?:unsubscribe|confirm|token)/i', $decoded)) { return ''; }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http','https'], true))) { return ''; }
        // No remote images, tracking pixels or personalised image URLs in the public archive.
        if ($image && (isset($parts['scheme']) || isset($parts['host']) || !preg_match('/\.(?:png|jpe?g|gif|webp|avif)$/i', $url))) { return ''; }
        return $url;
    }
    private static function style(string $value): string
    {
        $safe = [];
        foreach (explode(';', $value) as $declaration) {
            $pair = explode(':', $declaration, 2);
            if (count($pair) !== 2) { continue; }
            [$key, $val] = array_map('trim', $pair);
            $key = strtolower($key);
            if (!preg_match('/^(?:color|background-color|font-size|font-weight|font-style|font-family|text-align|text-decoration|line-height|padding(?:-(?:top|right|bottom|left))?|margin(?:-(?:top|right|bottom|left))?|border(?:-(?:top|right|bottom|left))?|border-collapse|width|max-width|height)$/', $key)) { continue; }
            if ($val !== '' && preg_match('/^[a-zA-Z0-9 #.,%\x27"-]+$/D', $val) && !preg_match('/(?:url|expression|behavior|javascript)/i', $val)) { $safe[] = $key . ':' . $val; }
        }
        return implode(';', $safe);
    }
    public static function html(string $html, string $subscriber = 'Subscriber'): string
    {
        if (strlen($html) > 2097152) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400); }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try { $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) { return ''; }
        $clean = function ($parent) use (&$clean, $subscriber) {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof \DOMText) { $node->nodeValue = self::redact($node->nodeValue, $subscriber); continue; }
                if (!($node instanceof \DOMElement)) { $parent->removeChild($node); continue; }
                $tag = strtolower($node->tagName);
                if (in_array($tag, self::DROP, true)) { $parent->removeChild($node); continue; }
                $clean($node);
                if (!in_array($tag, self::TAGS, true)) {
                    while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                    $parent->removeChild($node); continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $key = strtolower($attribute->name); $value = $attribute->value; $safe = '';
                    if (in_array($key, ['alt','title'], true)) { $safe = self::text($value, $subscriber); }
                    elseif ($key === 'style') { $safe = self::style($value); }
                    elseif (in_array($key, ['width','height','colspan','rowspan'], true) && preg_match('/^[1-9][0-9]{0,3}%?$/D', $value)) { $safe = $value; }
                    elseif ($tag === 'a' && $key === 'href') { $safe = self::url($value, false); }
                    elseif ($tag === 'img' && $key === 'src') { $safe = self::url($value, true); }
                    $node->removeAttributeNode($attribute);
                    if ($safe !== '') { $node->setAttribute($key, $safe); }
                }
                if ($tag === 'img' && (!$node->hasAttribute('src') || $node->getAttribute('width') === '1' || $node->getAttribute('height') === '1')) { $parent->removeChild($node); }
                elseif ($tag === 'a') { $node->setAttribute('rel', 'nofollow noopener noreferrer'); }
            }
        };
        $clean($body); $output = '';
        foreach ($body->childNodes as $child) { $output .= $dom->saveHTML($child); }
        return $output;
    }
}
