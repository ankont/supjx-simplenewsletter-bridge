<?php
/** Read-only check after the documented local archive browser fixtures are removed. */
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$ids=array_slice($argv,1);
if(!$ids || array_filter($ids,fn($id)=>!preg_match('/^[1-9][0-9]*$/D',$id)))throw new RuntimeException('Pass the recorded test user IDs as CLI arguments');
$owners=implode(',',$ids);
foreach(['users'=>'id IN ('.$owners.')','simplenewsletter_templates'=>'created_by IN ('.$owners.')','simplenewsletter_jobs'=>'created_by IN ('.$owners.')'] as $table=>$where) {
    if((int)$db->setQuery('SELECT COUNT(*) FROM #__'.$table.' WHERE '.$where)->loadResult()!==0)throw new RuntimeException('Remaining fixture '.$table);
}
if(is_file('/tmp/newsletterbridge-e2e-state.json'))throw new RuntimeException('Fixture state remains');
if($db->setQuery("SHOW TABLES LIKE 'nbtest_%'")->loadColumn())throw new RuntimeException('Integration tables remain');
if((int)$db->setQuery('SELECT COUNT(*) FROM #__newsletterbridge_archive a LEFT JOIN #__simplenewsletter_jobs j ON j.id=a.job_id WHERE j.id IS NULL')->loadResult())throw new RuntimeException('Orphaned archive fixture');
foreach([219=>2,224=>1] as $id=>$access) {
    $menu=$db->setQuery('SELECT published,access FROM #__menu WHERE id='.$id)->loadObject();
    if(!$menu || (int)$menu->published!==1 || (int)$menu->access!==$access)throw new RuntimeException('Menu access mismatch');
}
echo 'PASS: local test users, jobs and orphaned public copies absent; integration tables removed; Registered/Public menus intact.'.PHP_EOL;
