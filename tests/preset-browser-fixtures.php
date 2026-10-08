<?php
/** Opt-in local article preset fixtures. Run after e2e-fixtures setup; cleanup before its cleanup. */
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$file='/tmp/newsletterbridge-preset-browser-state.json';$action=$argv[1]??'setup';
$extension=$db->setQuery("SELECT extension_id,params FROM #__extensions WHERE element='com_newsletterbridge' AND type='component'")->loadObject();
if($action==='cleanup') {
    $state=json_decode(file_get_contents($file),true);$categoryId=(int)$state['category'];
    $current=json_decode($extension->params,true)?:[];
    if((int)($current['preset_category']??0)===$categoryId) {
        $original=json_decode($state['params'],true)?:[];
        if(array_key_exists('preset_category',$original))$current['preset_category']=$original['preset_category'];else unset($current['preset_category']);
        $db->setQuery('UPDATE #__extensions SET params='.$db->quote(json_encode($current)).' WHERE extension_id='.(int)$extension->extension_id)->execute();
    }
    $ids=$db->setQuery('SELECT id FROM #__content WHERE catid='.$categoryId)->loadColumn();
    foreach($ids as $id) {
        $db->setQuery('DELETE FROM #__newsletterbridge_presets WHERE article_id='.(int)$id)->execute();
        $db->setQuery('DELETE FROM #__workflow_associations WHERE item_id='.(int)$id.' AND extension='.$db->quote('com_content.article'))->execute();
        $db->setQuery('DELETE FROM #__history WHERE item_id='.$db->quote('com_content.article.'.(int)$id))->execute();
        $table=$app->bootComponent('com_content')->getMVCFactory()->createTable('Article','Administrator');$table->load((int)$id);$table->delete((int)$id);
    }
    $category=new \Joomla\CMS\Table\Category($db);$category->load($categoryId);$category->delete($categoryId);
    unlink($file);echo 'Temporary preset articles/category removed and preset setting restored.'.PHP_EOL;exit;
}
if(is_file($file))throw new RuntimeException('Clean previous preset browser fixtures');
$users=json_decode(file_get_contents('/tmp/newsletterbridge-e2e-state.json'),true);
$state=['params'=>$extension->params,'category'=>0];file_put_contents($file,json_encode($state));
$rules=[];foreach(['core.create','core.edit.own'] as $right)foreach($users['groups'] as $group)$rules[$right][(string)$group]=1;
$category=new \Joomla\CMS\Table\Category($db);$category->setLocation(1,'last-child');
$category->bind(['title'=>'Newsletter presets temporary browser test','alias'=>'nb-presets-browser-'.bin2hex(random_bytes(4)),
    'extension'=>'com_content','published'=>0,'access'=>2,'language'=>'*','params'=>'{}','metadata'=>'{}','rules'=>$rules]);
if(!$category->check()||!$category->store())throw new RuntimeException('Cannot create preset category');
$state['category']=(int)$category->id;file_put_contents($file,json_encode($state));
$params=json_decode($extension->params,true)?:[];$params['preset_category']=$state['category'];
$db->setQuery('UPDATE #__extensions SET params='.$db->quote(json_encode($params)).' WHERE extension_id='.(int)$extension->extension_id)->execute();
echo json_encode(['category'=>$state['category'],'author'=>$users['author']['id'],'sender'=>$users['sender']['id']]).PHP_EOL;
