<?php
/** Authorised disposable-site browser identity. No newsletter or subscriber writes. */
define('_JEXEC',1); define('JPATH_BASE','/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089';$_SERVER['SERVER_NAME']='localhost';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['PHP_SELF']='/index.php';
require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
$container=\Joomla\CMS\Factory::getContainer();
$container->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app=$container->get(\Joomla\CMS\Application\SiteApplication::class);\Joomla\CMS\Factory::$application=$app;
$app->loadIdentity(new \Joomla\CMS\User\User());$app->createExtensionNamespaceMap();(new ReflectionMethod($app,'initialiseApp'))->invoke($app);
$db=$container->get(\Joomla\Database\DatabaseInterface::class);
$statePath='/tmp/newsletterbridge-editor-web-state.json';
if(($argv[1]??'')==='cleanup'){
    if(!is_file($statePath)){echo "No editor web fixture.\n";exit;}
    $state=json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR);$id=(int)$state['id'];
    $db->setQuery('DELETE FROM #__session WHERE userid='.$id)->execute();
    $db->setQuery('DELETE FROM #__user_usergroup_map WHERE user_id='.$id)->execute();
    $db->setQuery('DELETE FROM #__user_profiles WHERE user_id='.$id)->execute();
    if(in_array($db->getPrefix().'action_logs',$db->getTableList(),true)) $db->setQuery('DELETE FROM #__action_logs WHERE user_id='.$id)->execute();
    $db->setQuery('DELETE FROM #__users WHERE id='.$id.' AND username='.$db->quote($state['username']))->execute();
    unlink($statePath);echo "Editor web fixture removed.\n";exit;
}
if(is_file($statePath))throw new RuntimeException('Clean the recorded editor web fixture first.');
$now=$db->quote(\Joomla\CMS\Factory::getDate()->toSql());
$article=(int)$db->setQuery("SELECT a.id FROM #__content a JOIN #__categories c ON c.id=a.catid WHERE a.state=1 AND a.access=1 AND c.published=1 AND c.access=1 AND (a.publish_up IS NULL OR a.publish_up<=".$now.") AND (a.publish_down IS NULL OR a.publish_down>=".$now.") AND NOT EXISTS (SELECT 1 FROM #__categories p WHERE p.lft<=c.lft AND p.rgt>=c.rgt AND (p.published!=1 OR p.access!=1)) ORDER BY (a.id=515) DESC,a.id LIMIT 1")->loadResult();
if(!$article)throw new RuntimeException('No public article available for read-only editor preview.');
$username='nb_editor_'.bin2hex(random_bytes(5));$password='NBridge-Context-2026-OnlyLocal!';
$user=new \Joomla\CMS\Table\User($db);
$user->bind(['name'=>'Temporary editor context test','username'=>$username,'email'=>$username.'@example.invalid','password'=>\Joomla\CMS\User\UserHelper::hashPassword($password),'block'=>0,'sendEmail'=>0,'registerDate'=>\Joomla\CMS\Factory::getDate()->toSql(),'params'=>'{"allowTourAutoStart":0}']);
if(!$user->check()||!$user->store())throw new RuntimeException('Cannot create editor browser fixture');
$state=['id'=>(int)$user->id,'username'=>$username];file_put_contents($statePath,json_encode($state));
$map=(object)['user_id'=>(int)$user->id,'group_id'=>8];$db->insertObject('#__user_usergroup_map',$map);
echo json_encode($state+['password'=>$password,'article'=>$article]);
