<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;
use Joomla\String\StringHelper;

/** Display-name matching only; never turn a supplied name into a path or article id. */
final class PresetNames
{
    public static function key(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (class_exists('\Normalizer')) { $name = \Normalizer::normalize($name, \Normalizer::FORM_C); }
        return StringHelper::strtolower($name);
    }
    public static function builtin(string $name, array $presets): ?string
    {
        $key = self::key($name);
        foreach ($presets as $preset) {
            foreach ($preset['names'] as $candidate) {
                if (self::key($candidate) === $key) { return $preset['id']; }
            }
        }
        return null;
    }
    public static function target(object $article, array $presets): ?string
    {
        // Remember a translated built-in name, but let an external article rename detach it.
        if (!empty($article->builtin_id) && isset($article->saved_name)
            && self::key($article->title) === self::key($article->saved_name)) {
            foreach ($presets as $preset) { if ($preset['id'] === $article->builtin_id) { return $preset['id']; } }
        }
        return self::builtin($article->title, $presets);
    }
    public static function merge(array $presets, array $articles): array
    {
        $entries = [];
        foreach ($presets as $preset) {
            $entries[$preset['id']] = ['id' => $preset['id'], 'title' => $preset['label'], 'article_id' => 0];
        }
        // Caller supplies readable rows newest first; duplicate article names appear only once.
        $seen = [];
        foreach ($articles as $article) {
            $target = self::target($article, $presets);
            $key = $target !== null ? 'builtin:' . $target : self::key($article->title);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $id = $target ?? 'article-' . (int) $article->id;
            $label = $target !== null ? $entries[$target]['title'] : $article->title;
            $entries[$id] = ['id' => $id, 'title' => $label, 'article_id' => (int) $article->id];
        }
        return array_values($entries);
    }
}
