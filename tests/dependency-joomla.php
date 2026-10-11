<?php
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$app->loadDocument();
use SuperSoft\Component\Newsletterbridge\Site\Service\Dependency;
class CompatibilityAdmin extends \Joomla\CMS\User\User {public function authorise($action,$assetname=null){return $action==='core.manage' && $assetname==='com_newsletterbridge';}}
$user=new CompatibilityAdmin();$user->id=900000005;$user->guest=0;$app->loadIdentity($user);
$row=$db->setQuery("SELECT extension_id,manifest_cache FROM #__extensions WHERE element='com_simplenewsletter' AND type='component'")->loadObject();
$base=json_decode($row->manifest_cache,true)?:[];$checks=0;
$db->transactionStart();
try{
 foreach(['99.0.0',''] as $version){
  $cache=$base;$cache['version']=$version;
  $db->setQuery('UPDATE #__extensions SET manifest_cache='.$db->quote(json_encode($cache)).' WHERE extension_id='.(int)$row->extension_id)->execute();
  (new ReflectionProperty(Dependency::class,'factory'))->setValue(null,null);
  Dependency::factory();$checks++;
  if(!in_array('COM_NEWSLETTERBRIDGE_COMPAT_VERSION_WARNING',Dependency::warnings(),true))throw new RuntimeException('Missing advisory');$checks++;
  $controller=$app->bootComponent('com_newsletterbridge')->getMVCFactory()->createController('Display','Administrator',[],$app,$app->getInput());
  ob_start();$controller->display();$html=ob_get_clean();
  if(!str_contains($html,'alert-warning'))throw new RuntimeException('Admin warning not rendered');$checks++;
 }
 echo 'PASS: '.$checks.' real Joomla compatibility/admin assertions; zero mail; metadata restored in finally.'.PHP_EOL;
}finally{$db->transactionRollback();(new ReflectionProperty(Dependency::class,'factory'))->setValue(null,null);}