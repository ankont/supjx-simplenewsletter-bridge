<?php
// Read-only bootstrap: invoke from inside the selected Joomla container.
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/html');
$_SERVER['HTTP_HOST'] = 'localhost:8089'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/index.php'; $_SERVER['SCRIPT_NAME'] = '/index.php';
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
$c = \Joomla\CMS\Factory::getContainer();
$c->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app = $c->get(\Joomla\CMS\Application\SiteApplication::class);
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();
(new ReflectionMethod($app, 'initialiseApp'))->invoke($app);
define('JPATH_COMPONENT', JPATH_SITE . '/components/com_newsletterbridge');
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_newsletterbridge');
$app->getLanguage()->load('com_newsletterbridge', JPATH_SITE);
$db = $c->get(\Joomla\Database\DatabaseInterface::class);
$q = $db->getQuery(true)->select($db->quoteName(['element','enabled','manifest_cache']))->from($db->quoteName('#__extensions'))->where($db->quoteName('element') . ' IN (' . $db->quote('com_simplenewsletter') . ',' . $db->quote('com_newsletterbridge') . ')');
$rows = $db->setQuery($q)->loadObjectList();
foreach ($rows as $row) { echo $row->element . ' enabled=' . $row->enabled . ' version=' . (json_decode($row->manifest_cache)->version ?? '?') . PHP_EOL; }
try {
    $factory = \SuperSoft\Component\Newsletterbridge\Site\Service\Dependency::factory();
    echo 'Dependency factory: ' . get_class($factory) . PHP_EOL;
} catch (\Throwable $e) { echo 'DEPENDENCY FAILURE: ' . $e->getMessage() . PHP_EOL; }
echo 'Site application: ' . $app->getName() . PHP_EOL;
echo 'Bridge controller: ' . get_class($app->bootComponent('com_newsletterbridge')->getMVCFactory()->createController('Display','Site', [],$app,$app->getInput())) . PHP_EOL;
