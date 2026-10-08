<?php
/** Synthetic sent snapshots only; no recipients, queue rows or mail calls. */
ob_start(); require '/tmp/newsletterbridge-inspect.php'; ob_end_clean();
$stateFile='/tmp/newsletterbridge-e2e-state.json';
$state=json_decode(file_get_contents($stateFile),true);
if(isset($state['archiveJob']))throw new RuntimeException('Archive fixtures already exist');
$user=$c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($state['author']['id']);$app->loadIdentity($user);
$bridge=new \SuperSoft\Component\Newsletterbridge\Site\Service\Bridge();
$id=$bridge->save(0,['title'=>'Archive temporary test','subject'=>'Temporary test issue','body'=>'<p>Temporary test body</p>']);
$archive=new \SuperSoft\Component\Newsletterbridge\Site\Service\Archive();
for($i=1;$i<=22;$i++) {
    $job=(object)['template_id'=>$id,'subject'=>'Archive test '.$i.' {name}','body'=>'<h2>Γεια σας {name}</h2><p>Τεχνητό newsletter δοκιμής. person@example.invalid</p><a href="{unsubscribe_link}">Unsubscribe</a>',
      'state'=>2,'total'=>1,'sent'=>1,'failed'=>0,'created'=>\Joomla\CMS\Factory::getDate()->toSql(),'finished'=>\Joomla\CMS\Factory::getDate()->toSql(),'created_by'=>(int)$user->id];
    $db->insertObject('#__simplenewsletter_jobs',$job,'id');
    if($i===22){$state['archiveJob']=(int)$job->id;continue;}
    $archive->save((int)$job->id,0,['title'=>'Temporary public issue '.$i,'subject'=>'Temporary issue '.$i,'body'=>$job->body],true,true);
}
$state['archiveTemplate']=$id;
$menuId=(int)$db->setQuery("SELECT id FROM #__menu WHERE client_id=0 AND link='index.php?option=com_newsletterbridge&view=archive' AND published=1")->loadResult();
if(!$menuId) {
    $component=(int)$db->setQuery("SELECT extension_id FROM #__extensions WHERE element='com_newsletterbridge' AND type='component'")->loadResult();
    $menu=new \Joomla\CMS\Table\Menu($db);$menu->setLocation(1,'last-child');
    $menu->bind(['menutype'=>'mainmenu','title'=>'Newsletter','alias'=>'newsletters','path'=>'newsletters','link'=>'index.php?option=com_newsletterbridge&view=archive',
      'type'=>'component','published'=>1,'parent_id'=>1,'component_id'=>$component,'access'=>1,'client_id'=>0,'language'=>'*','params'=>'{}']);
    if(!$menu->check()||!$menu->store())throw new RuntimeException('Cannot create public menu');$menuId=(int)$menu->id;
}
$state['publicMenuId']=$menuId;
file_put_contents($stateFile,json_encode($state));
echo json_encode(['template'=>$id,'draftJob'=>$state['archiveJob'],'publicMenuId'=>$menuId]).PHP_EOL;
