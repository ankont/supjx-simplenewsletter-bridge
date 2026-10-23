<?php
defined('_JEXEC') or die;

/** Remove only bridge-owned files superseded by the email provider protocol. */
class Com_NewsletterbridgeInstallerScript
{
    public function postflight($type, $adapter): bool
    {
        if (!in_array($type, ['install', 'update'], true)) { return true; }
        $base = JPATH_ROOT . '/components/com_newsletterbridge/';
        foreach (['src/Service/SourcesEmail.php', 'layouts/email/sources.php'] as $relative) {
            $path = $base . $relative;
            if (is_file($path) && !unlink($path)) { throw new \RuntimeException('Unable to remove obsolete bridge file: ' . $relative); }
        }
        return true;
    }
}
