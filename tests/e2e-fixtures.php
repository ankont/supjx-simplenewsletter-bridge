<?php
/** Opt-in local browser fixtures. Removes only recorded test user/group/newsletter/job IDs. */
require '/tmp/newsletterbridge-inspect.php';
$stateFile='/tmp/newsletterbridge-e2e-state.json';
$action=$argv[1]??'setup';
if($action==='cleanup') {
    $state=json_decode(file_get_contents($stateFile),true);
    if(isset($state['override'])) { $resolved=realpath($state['override']); $templates=realpath(JPATH_SITE.'/templates').DIRECTORY_SEPARATOR; if($resolved && str_starts_with($resolved,$templates))unlink($resolved); }
    foreach ($state['assets'] as $name=>$rules) {
        $asset=new \Joomla\CMS\Table\Asset($db); $asset->loadByName($name); $asset->rules=$rules; $asset->store();
    }
    foreach ($state['users'] as $id) {
        $db->setQuery('DELETE FROM #__user_usergroup_map WHERE user_id = '.(int)$id)->execute();
        $db->setQuery('DELETE FROM #__session WHERE userid = '.(int)$id)->execute();
        $db->setQuery('DELETE FROM #__users WHERE id = '.(int)$id)->execute();
    }
    foreach ($state['groups'] as $id) { $g=new \Joomla\CMS\Table\Usergroup($db); if($g->load($id))$g->delete($id); }
    foreach ($state['users'] as $owner) {
        $ids=$db->setQuery('SELECT id FROM #__simplenewsletter_jobs WHERE created_by = '.(int)$owner)->loadColumn();
        foreach($ids as $id) { $db->setQuery('DELETE FROM #__simplenewsletter_queue WHERE job_id = '.(int)$id)->execute(); $db->setQuery('DELETE FROM #__simplenewsletter_jobs WHERE id = '.(int)$id)->execute(); }
        $db->setQuery('DELETE FROM #__simplenewsletter_templates WHERE created_by = '.(int)$owner)->execute();
    }
    unlink($stateFile); echo "Browser fixtures removed; original ACL restored.\n"; exit;
}
if(is_file($stateFile))throw new RuntimeException('Clean previous recorded browser fixtures first.');
$state=['users'=>[],'groups'=>[],'assets'=>[]];
foreach(['com_newsletterbridge','com_simplenewsletter'] as $name) { $asset=new \Joomla\CMS\Table\Asset($db); if(!$asset->loadByName($name))throw new RuntimeException('Missing component asset'); $state['assets'][$name]=$asset->rules; }
file_put_contents($stateFile,json_encode($state));
foreach(['author','sender'] as $role) {
    $g=new \Joomla\CMS\Table\Usergroup($db);
    $g->bind(['title'=>'Newsletter Bridge temporary '.$role,'parent_id'=>2]);
    if(!$g->check()||!$g->store())throw new RuntimeException('Cannot create group');
    $group=(int)$g->id; $state['groups'][]=$group; file_put_contents($stateFile,json_encode($state));
    $u=new \Joomla\CMS\Table\User($db);
    $u->bind(['name'=>'Bridge Test '.$role,'username'=>'newsletterbridge_test_'.$role,'email'=>'bridge-'.$role.'@example.invalid',
      'password'=>\Joomla\CMS\User\UserHelper::hashPassword('NBridge-2026-OnlyLocal!'),'block'=>0,'sendEmail'=>0,
      'registerDate'=>\Joomla\CMS\Factory::getDate()->toSql(),'params'=>'{}']);
    if(!$u->check()||!$u->store())throw new RuntimeException('Cannot create fixture user');
    $id=(int)$u->id; $state['users'][]=$id; $state[$role]=['id'=>$id,'group'=>$group]; file_put_contents($stateFile,json_encode($state));
    $map=(object)['user_id'=>$id,'group_id'=>$group];$db->insertObject('#__user_usergroup_map',$map);
    $asset=new \Joomla\CMS\Table\Asset($db);$asset->loadByName('com_newsletterbridge');$rules=json_decode($asset->rules,true)?:[];
    $grants=['newsletterbridge.access','core.create','core.edit.own'];
    if($role==='sender')$grants=array_merge($grants,['core.edit.state','newsletterbridge.send']);
    foreach($grants as $grant)$rules[$grant][(string)$group]=1;
    $asset->rules=json_encode($rules);$asset->store();
    if($role==='sender') {
        $asset=new \Joomla\CMS\Table\Asset($db);$asset->loadByName('com_simplenewsletter');$rules=json_decode($asset->rules,true)?:[];
        $rules['simplenewsletter.send'][(string)$group]=1;$asset->rules=json_encode($rules);$asset->store();
    }
}
// A permanent Registered menu item exposes the component; component ACL still governs all access.
$component=(int)$db->setQuery("SELECT extension_id FROM #__extensions WHERE element='com_newsletterbridge' AND type='component'")->loadResult();
$menuId=(int)$db->setQuery("SELECT id FROM #__menu WHERE client_id=0 AND link='index.php?option=com_newsletterbridge&view=newsletters' AND published=1")->loadResult();
if(!$menuId) {
    $menu=new \Joomla\CMS\Table\Menu($db);$menu->setLocation(1,'last-child');
    $menu->bind(['menutype'=>'mainmenu','title'=>'Newsletter management','alias'=>'newsletter-management','path'=>'newsletter-management','link'=>'index.php?option=com_newsletterbridge&view=newsletters',
      'type'=>'component','published'=>1,'parent_id'=>1,'component_id'=>$component,'access'=>2,'client_id'=>0,'language'=>'*','params'=>'{}']);
    if(!$menu->check()||!$menu->store())throw new RuntimeException('Cannot create bridge menu item');$menuId=(int)$menu->id;
}
$state['menuId']=$menuId;file_put_contents($stateFile,json_encode($state));
echo json_encode(['menuId'=>$menuId,'authorId'=>$state['author']['id'],'senderId'=>$state['sender']['id']]).PHP_EOL;
