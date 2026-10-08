<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

final class InputGuard
{
    /** Strict POST token check, without Joomla's new-session redirect branch. */
    public static function csrf($app): bool
    {
        $input = $app->getInput();
        return $input->server->getString('REQUEST_METHOD') === 'POST'
            && $input->post->getString(\Joomla\CMS\Session\Session::getFormToken(), '') === '1';
    }
    /** Only a canonical local bridge list route is accepted; no arbitrary redirect tasks. */
    public static function returnUrl(string $url, string $fallback): string
    {
        if (preg_match('~^index\.php\?option=com_newsletterbridge&view=newsletters(?:&Itemid=[1-9][0-9]*)?$~D', $url)) {
            return $url;
        }
        return $fallback;
    }
    public static function id($value, bool $allowNew = false): int
    {
        if (!is_scalar($value) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', (string) $value)
            || (float) $value > 2147483647 || (!$allowNew && (int) $value < 1)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
        }
        return (int) $value;
    }
    public static function newsletter(array $raw): array
    {
        $out = [];
        foreach (['title', 'subject', 'body'] as $key) {
            if (!isset($raw[$key]) || !is_string($raw[$key])) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
            }
            $out[$key] = trim($raw[$key]);
        }
        foreach (['title', 'subject'] as $key) {
            $length = preg_match_all('/./us', $out[$key]);
            if ($out[$key] === '' || $length === false || $length > 255 || preg_match('/[\x00-\x1f\x7f]/', $out[$key])) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
            }
            $out[$key] = trim(strip_tags($out[$key]));
            if ($out[$key] === '') { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400); }
        }
        if (preg_match('//u', $out['body']) !== 1 || strlen($out['body']) > 2000000 || (trim(strip_tags($out['body'])) === '' && stripos($out['body'], '<img') === false)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
        }
        return $out;
    }
    public static function state($value): int
    {
        if (!in_array($value, [0, 1, '0', '1'], true)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
        }
        return (int) $value;
    }
}
