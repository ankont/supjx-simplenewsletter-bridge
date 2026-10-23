<?php
/** Included by joomla-integration.php. Installs and removes a synthetic provider in finally. */
use SuperSoft\Component\Newsletterbridge\Site\Service\EmailPreparation;

$pluginDir=JPATH_ROOT.'/plugins/newsletter/newsletterbridgefixture';
$pluginFile=$pluginDir.'/newsletterbridgefixture.php';
$pluginId=0; $newDirs=[];
check(!is_dir($pluginDir),'test provider directory must not preexist');
check(!(int)$original->setQuery("SELECT COUNT(*) FROM #__extensions WHERE type='plugin' AND folder='newsletter' AND element='newsletterbridgefixture'")->loadResult(),'test provider registration must not preexist');
$pluginCache=new ReflectionProperty(\Joomla\CMS\Plugin\PluginHelper::class,'plugins');
$savedPlugins=$pluginCache->getValue();
try {
    denied(fn()=>EmailPreparation::render('{sources:{"version":2}}','test'),400,'Sources without provider fails closed');
    $user->testRights['com_newsletterbridge:core.edit.state']=true;
    for($dir=$pluginDir;!is_dir($dir);$dir=dirname($dir)) $newDirs[]=$dir;
    mkdir($pluginDir,0755,true);
    check(copy(__DIR__.'/newsletterbridgefixture.php',$pluginFile),'copy test-only provider');
    $extension=(object)['name'=>'Newsletter Bridge fixture','type'=>'plugin','element'=>'newsletterbridgefixture','folder'=>'newsletter',
        'client_id'=>0,'enabled'=>1,'access'=>1,'protected'=>0,'manifest_cache'=>'{}','params'=>'{}','custom_data'=>'','system_data'=>'','ordering'=>99999,'state'=>0];
    $original->insertObject('#__extensions',$extension,'extension_id'); $pluginId=(int)$extension->extension_id;
    $pluginCache->setValue(null,null);
    $raw='{bridgefixture:{"article":1}}';
    $idProvider=$bridge->save(0,['title'=>'Provider protocol fixture','subject'=>'Provider {name}','body'=>'Fixture','published'=>1]);
    $fixture=(object)['id'=>$idProvider,'body'=>$raw]; $db->updateObject('#__simplenewsletter_templates',$fixture,'id');
    $preview=$bridge->preview($idProvider);
    check(class_exists('PlgNewsletterNewsletterbridgefixture',false),'real Joomla plugin loader invoked');
    check(str_contains($preview,'Original provider article Sample'),'preview provider then subscriber placeholders');
    check(str_contains($preview,'src="'.$siteRoot.'images/provider.jpg"'),'provider output gets absolute image URLs');
    check($app->getIdentity()===$user,'identity restored after real provider');
    $count=count($mock->messages); $bridge->test($idProvider,'author@example.invalid');
    check(count($mock->messages)===$count+1 && str_contains($mock->messages[$count]['body'],'Original provider article Sample'),'test output through memory transport');
    $jobProvider=$bridge->start($idProvider,true);
    $snapshot=$db->setQuery('SELECT body FROM #__simplenewsletter_jobs WHERE id='.(int)$jobProvider['jobId'])->loadResult();
    check(str_contains($snapshot,'Original provider article {name}') && !str_contains($snapshot,'{bridgefixture:'),'send snapshot preserves subscriber placeholder');
    $calls=PlgNewsletterNewsletterbridgefixture::$calls;
    PlgNewsletterNewsletterbridgefixture::$title='Changed provider article';
    $count=count($mock->messages); $jobProvider=$bridge->process($jobProvider['jobId']);
    check(PlgNewsletterNewsletterbridgefixture::$calls===$calls,'no provider dispatch in process');
    check(str_contains($mock->messages[$count]['body'],'Original provider article Sample 1'),'upstream batch uses original snapshot');
    $jobProvider=$bridge->process($jobProvider['jobId']);
    check(PlgNewsletterNewsletterbridgefixture::$calls===$calls && $jobProvider['sent']===2,'resume does not re-render');
    $bridge->cancel($jobProvider['jobId']);
    $fixture=(object)['id'=>$jobProvider['jobId'],'state'=>1,'body'=>$raw]; $db->updateObject('#__simplenewsletter_jobs',$fixture,'id');
    $count=count($mock->messages);
    denied(fn()=>$bridge->process($jobProvider['jobId']),409,'old unresolved snapshot blocked without provider dispatch');
    check(count($mock->messages)===$count && PlgNewsletterNewsletterbridgefixture::$calls===$calls,'old snapshot causes no mail or rendering');
    $bridge->cancel($jobProvider['jobId']);
    foreach(['throw'=>503,'bad-policy'=>503,'unresolved'=>400] as $mode=>$code) {
        PlgNewsletterNewsletterbridgefixture::$mode=$mode;
        $count=count($mock->messages);
        denied(fn()=>$bridge->test($idProvider,'author@example.invalid'),$code,'provider failure '.$mode);
        check(count($mock->messages)===$count && $app->getIdentity()===$user,'no mail and identity restored after '.$mode);
    }
    $jobs=$bridge->jobs($idProvider)['total'];
    denied(fn()=>$bridge->start($idProvider,true),400,'unresolved provider result prevents queue');
    check($bridge->jobs($idProvider)['total']===$jobs,'failed preparation creates no job');
    PlgNewsletterNewsletterbridgefixture::$mode='render';
    check(EmailPreparation::render('Ordinary newsletter','test')==='Ordinary newsletter','provider no-op on unrelated content');
} finally {
    $app->loadIdentity($user);
    if($pluginId) $original->setQuery('DELETE FROM #__extensions WHERE extension_id='.$pluginId)->execute();
    $pluginCache->setValue(null,$savedPlugins);
    if(is_file($pluginFile)) unlink($pluginFile);
    foreach($newDirs as $dir) { if(is_dir($dir) && count(scandir($dir))===2) rmdir($dir); }
}
