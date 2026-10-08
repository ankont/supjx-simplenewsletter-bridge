<?php
/** Negative real-MVC dispatch checks. No mail or successful data mutation. */
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$state=json_decode(file_get_contents('/tmp/newsletterbridge-e2e-state.json'),true);
$author=$c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($state['author']['id']);$app->loadIdentity($author);
$input=$app->getInput();$token=\Joomla\CMS\Session\Session::getFormToken();
$input->post->set('id',(string)$state['archiveJob']);$input->post->set('revision','0');
$input->post->set('jform',['title'=>'Must not publish','subject'=>'Must not publish','body'=>'<p>Must not publish</p>']);
$factory=$app->bootComponent('com_newsletterbridge')->getMVCFactory();$count=0;
$cases=[['save','POST',false,403],['publish','POST',false,403],['withdraw','POST',false,403],['save','GET',true,403],['publish','GET',true,403],['withdraw','GET',true,403],['display','POST',true,404],['getModel','POST',true,404],['publish','POST',true,400]];
foreach($cases as [$task,$method,$csrf,$expected]) {
    $input->server->set('REQUEST_METHOD',$method);$input->post->set($token,$csrf?'1':null);
    try {$factory->createController('Publication','Site',[],$app,$input)->execute($task);throw new LogicException('Unexpected success: '.$task);}
    catch(RuntimeException $e){if($e->getCode()!==$expected)throw new LogicException('Wrong denial '.$task.' '.$e->getCode());$count++;}
}
$sender=$c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($state['sender']['id']);$app->loadIdentity($sender);
$input->post->set('reviewed','1');
foreach(['save','publish','withdraw'] as $task) {
    try {$factory->createController('Publication','Site',[],$app,$input)->execute($task);throw new LogicException('Foreign resource allowed');}
    catch(RuntimeException $e){if($e->getCode()!==403)throw $e;$count++;}
}
echo 'PASS: '.$count.' archive controller cases; no mail and no successful mutation.'.PHP_EOL;
