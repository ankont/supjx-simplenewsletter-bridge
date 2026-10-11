<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;

final class Dependency
{
    private static $factory;
    public static function factory()
    {
        if (self::$factory) { return self::$factory; }
        try {
            $version = new Version();
            if (!in_array((int) Version::MAJOR_VERSION, [5, 6], true)
                || version_compare($version->getShortVersion(), '5.4.0', '<')) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_JOOMLA', 503);
            }
            if (!ComponentHelper::isEnabled('com_simplenewsletter')) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_MISSING', 503);
            }
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            foreach ([
                'templates' => ['id','title','subject','body','published','created_by','created','modified','checked_out','checked_out_time'],
                'jobs' => ['id','template_id','subject','body','state','total','sent','failed','created_by','created','finished'],
                'queue' => ['id','job_id','subscriber_id','email','name','token','state','error','sent_date'],
                'subscribers' => ['id','email','name','token','state'],
            ] as $table => $columns) {
                $actual = $db->getTableColumns('#__simplenewsletter_' . $table, false);
                if (array_diff($columns, array_keys($actual))) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_SCHEMA', 503); }
                // Job creation uses transactions; do not silently accept non-transactional tables.
                $name = $db->replacePrefix('#__simplenewsletter_' . $table);
                $engine = $db->setQuery('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $db->quote($name))->loadResult();
                if (strtoupper((string) $engine) !== 'INNODB') { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_STORAGE', 503); }
            }
            $factory = Factory::getApplication()->bootComponent('com_simplenewsletter')->getMVCFactory();
            $model = $factory->createModel('Send', 'Administrator', ['ignore_request' => true]);
            $template = $factory->createModel('Template', 'Administrator', ['ignore_request' => true]);
            if (!$model || !$template) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_API', 503); }
            foreach (['createJob','processBatch','getPendingCount','getJob','cancelJob'] as $method) {
                if (!is_callable([$model, $method])) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_API', 503); }
            }
            foreach (['getTable','checkout','checkin'] as $method) {
                if (!is_callable([$template, $method])) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_API', 503); }
            }
            foreach ([
                'SaintArt\\Component\\SimpleNewsletter\\Administrator\\Helper\\PresetHelper' => ['getPresets','getBody'],
                'SaintArt\\Component\\SimpleNewsletter\\Administrator\\Helper\\NewsletterHelper' => ['replaceTags','getSender','getReplyTo'],
            ] as $helper => $methods) {
                foreach ($methods as $method) {
                    if (!is_callable([$helper, $method])) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_DEPENDENCY_API', 503); }
                }
            }
            Factory::getApplication()->getLanguage()->load('com_simplenewsletter', JPATH_ADMINISTRATOR, 'en-GB', true);
            Factory::getApplication()->getLanguage()->load('com_simplenewsletter', JPATH_ADMINISTRATOR, null, true);
            self::$factory = $factory;
            return $factory;
        } catch (\Throwable $e) {
            $key = in_array($e->getMessage(), ['COM_NEWSLETTERBRIDGE_DEPENDENCY_MISSING','COM_NEWSLETTERBRIDGE_DEPENDENCY_JOOMLA',
                'COM_NEWSLETTERBRIDGE_DEPENDENCY_SCHEMA','COM_NEWSLETTERBRIDGE_DEPENDENCY_STORAGE','COM_NEWSLETTERBRIDGE_DEPENDENCY_API'], true)
                ? $e->getMessage() : 'COM_NEWSLETTERBRIDGE_DEPENDENCY_INCOMPATIBLE';
            throw new \RuntimeException($key, 503);
        }
    }

    /** Advisory diagnostics for administrators; never a frontend access gate. */
    public static function warnings(): array
    {
        $warnings = [];
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_simplenewsletter'));
        $cache = json_decode((string) $db->setQuery($query)->loadResult(), true);
        $contract = json_decode((string) file_get_contents(JPATH_ADMINISTRATOR . '/components/com_newsletterbridge/compatibility.json'), true);
        if (($cache['version'] ?? '') !== ($contract['version'] ?? null)) {
            $warnings[] = 'COM_NEWSLETTERBRIDGE_COMPAT_VERSION_WARNING';
        }
        $base = JPATH_ADMINISTRATOR . '/components/com_simplenewsletter/';
        foreach ($contract['sha256'] ?? [] as $file => $hash) {
            if (!is_readable($base . $file)
                || !hash_equals($hash, hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($base . $file))))) {
                $warnings[] = 'COM_NEWSLETTERBRIDGE_COMPAT_FILES_WARNING';
                break;
            }
        }
        return $warnings;
    }
}
