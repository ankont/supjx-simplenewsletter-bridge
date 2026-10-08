<?php
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$stateFile='/tmp/newsletterbridge-e2e-state.json';$state=json_decode(file_get_contents($stateFile),true);
$template=$db->setQuery('SELECT template FROM #__template_styles WHERE client_id=0 AND home=1')->loadResult();
if(!preg_match('/^[a-z0-9_-]+$/i',$template))throw new RuntimeException('Unexpected template');
$target=JPATH_SITE.'/templates/'.$template.'/html/com_newsletterbridge/archive/default.php';
if(file_exists($target))throw new RuntimeException('Existing override will not be replaced');
if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);
$state['override']=$target;file_put_contents($stateFile,json_encode($state));
file_put_contents($target,'<?php defined("_JEXEC") or die; echo "<p data-nb-override>Archive override test</p>"; require JPATH_SITE . "/components/com_newsletterbridge/tmpl/archive/default.php";');
echo 'Temporary archive override installed.'.PHP_EOL;
