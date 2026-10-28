<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

/** Version 1 email-output protocol; providers own their syntax and rendering. */
final class EmailPreparation
{
    public const EVENT = 'onNewsletterPrepareEmail';
    public const MAX_BYTES = 2000000;
    private const PLACEHOLDERS = ['name','email','unsubscribe_link','confirm_link','sitename','siteurl','date'];

    public static function render(string $html, string $purpose): string
    {
        if (!in_array($purpose, ['preview','test','send'], true)) {
            throw new \InvalidArgumentException('Invalid email preparation purpose');
        }
        self::size($html);
        // No providers: no event, user switch or extension boot is needed.
        if (!PluginHelper::getPlugin('newsletter')) { self::assertPrepared($html); return $html; }
        $app = Factory::getApplication();
        if (!$app->isClient('site') && !($app->isClient('administrator') && $app->getIdentity()->authorise('core.manage', 'com_newsletterbridge'))) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_FAILED', 503);
        }
        // Legacy item/token/depth hints remain for older providers. The host
        // enforces bytes only; providers own selection and recursion policy.
        $policy = ['version' => 1, 'purpose' => $purpose, 'publicOnly' => true,
            'rootUrl' => Uri::root(), 'limits' => ['maxBytes' => self::MAX_BYTES, 'maxItems' => 60, 'maxTokens' => 8, 'maxDepth' => 3]];
        $document = new Registry($policy + ['html' => $html, 'usage' => ['items' => 0, 'tokens' => 0], 'errors' => [], 'unresolved' => []]);
        $identity = $app->getIdentity();
        try {
            // Providers must not resolve content with an author's elevated access.
            $app->loadIdentity(new User());
            PluginHelper::importPlugin('newsletter');
            $event = new Event(self::EVENT, ['context' => 'com_newsletterbridge.newsletter', 'document' => $document]);
            $app->getDispatcher()->dispatch(self::EVENT, $event);
        } catch (\Throwable $error) {
            // Provider errors must not leak configuration or article data to authors.
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_FAILED', 503);
        } finally {
            $app->loadIdentity($identity);
        }
        // Registry stores nested arrays as objects; compare through its array representation.
        $data = $document->toArray();
        if (array_diff(array_keys($data), array_merge(array_keys($policy), ['html','usage','errors','unresolved']))
            || !is_array($data['usage'] ?? null) || array_diff(array_keys($data['usage']), ['items','tokens'])) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_INVALID', 503);
        }
        foreach ($policy as $key => $value) {
            if (($data[$key] ?? null) !== $value) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_INVALID', 503); }
        }
        foreach (['items', 'tokens'] as $key) {
            $value = $data['usage'][$key] ?? null;
            if (!is_int($value) || $value < 0) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_LIMIT', 400); }
        }
        if (!is_array($data['errors'] ?? null) || !is_array($data['unresolved'] ?? null) || !is_string($data['html'] ?? null)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_INVALID', 503);
        }
        if ($data['errors']) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_FAILED', 503); }
        if ($data['unresolved']) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_UNRESOLVED', 400); }
        self::size($data['html']);
        self::assertPrepared($data['html']);
        return $data['html'];
    }

    private static function size(string $html): void
    {
        if (strlen($html) > self::MAX_BYTES || preg_match('//u', $html) !== 1) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_LIMIT', 400);
        }
    }

    /** Never dispatch providers during resume, or mail unresolved extension markup. */
    public static function assertPrepared(string $html, bool $snapshot = false): void
    {
        // CSS braces are not extension tokens. Scan the rest, including attributes
        // and comments, so malformed/hidden configuration cannot escape in email.
        $text = preg_replace('~<(style|script)\b[^>]*>[\s\S]*?</\1\s*>~i', '', $html);
        preg_match_all('/\{([a-z][a-z0-9_.-]*)(?=\s*[:}])/i', $text, $matches);
        foreach ($matches[1] as $name) {
            if (!in_array(strtolower($name), self::PLACEHOLDERS, true)) {
                throw new \RuntimeException($snapshot ? 'COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_SNAPSHOT' : 'COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_UNRESOLVED', $snapshot ? 409 : 400);
            }
        }
    }
}
