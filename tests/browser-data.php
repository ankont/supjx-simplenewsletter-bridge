<?php
require '/tmp/newsletterbridge-inspect.php';
$file='/tmp/newsletterbridge-e2e-state.json';$state=json_decode(file_get_contents($file),true);
for($i=1;$i<=23;$i++) {
 $row=(object)['title'=>'NB Pagination fixture '.$i,'subject'=>'Temporary sample','body'=>'<p>Sample</p>','published'=>0,'created_by'=>$state['author']['id'],'created'=>\Joomla\CMS\Factory::getDate()->toSql()];
 $db->insertObject('#__simplenewsletter_templates',$row,'id');
}
$template=$app->getTemplate();
$directory=JPATH_SITE.'/templates/'.$template.'/html/com_newsletterbridge/newsletters';
$override=$directory.'/default.php';
if(!is_file($override)) {
 if(!is_dir($directory))mkdir($directory,0755,true);
 file_put_contents($override,'<?php defined("_JEXEC") or die; echo "<div data-nb-override-test=1></div>"; require JPATH_COMPONENT . "/tmpl/newsletters/default.php";');
 $state['override']=$override;file_put_contents($file,json_encode($state));echo 'Temporary native override created.'.PHP_EOL;
}
echo 'Pagination fixtures created.'.PHP_EOL;
