<?php
/** Opt-in test runner for the authorised local Joomla. Mail transport is always mocked.
 * Creates empty isolated newsletter tables, copies no subscriber records, drops them in finally.
 * Run: php /tmp/newsletterbridge-integration.php /var/www/html
 */
define('_JEXEC', 1);
define('JPATH_BASE', $argv[1] ?? '/var/www/html');
if (in_array('--administrator', $argv, true)) { define('JPATH_THEMES', JPATH_BASE . '/administrator/templates'); }
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['SERVER_NAME']='localhost'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['PHP_SELF']='/index.php';
require JPATH_BASE . '/includes/defines.php'; require JPATH_BASE . '/includes/framework.php';
$c = \Joomla\CMS\Factory::getContainer();
$c->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app = $c->get(\Joomla\CMS\Application\SiteApplication::class); \Joomla\CMS\Factory::$application=$app;
$app->loadIdentity(new \Joomla\CMS\User\User());
$app->createExtensionNamespaceMap(); (new ReflectionMethod($app,'initialiseApp'))->invoke($app);
define('JPATH_COMPONENT',JPATH_SITE . '/components/com_newsletterbridge');
define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_ADMINISTRATOR . '/components/com_newsletterbridge');
$app->getInput()->set('option','com_newsletterbridge');
$app->getLanguage()->load('com_newsletterbridge',JPATH_SITE,'en-GB',true);
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
use SuperSoft\Component\Newsletterbridge\Site\Service\Dependency;
$checks=0;
function check($ok,$label) { global $checks; $checks++; if(!$ok) throw new RuntimeException('FAIL: '.$label); }
function denied(callable $work,int $code,string $label) {
    try { $work(); } catch (RuntimeException $e) { check($e->getCode()===$code,$label); return; }
    throw new RuntimeException('FAIL accepted: '.$label);
}
class BridgeTestUser extends \Joomla\CMS\User\User {
    public $testRights=[];
    public function authorise($action,$assetname=null) { return isset($this->testRights[$assetname.':'.$action]); }
}
$user=new BridgeTestUser(); $user->id=900000001; $user->guest=0; $user->groups=[2]; $user->name='Bridge test'; $user->email='author@example.invalid';
foreach (['newsletterbridge.access','core.create','core.edit.own','core.edit.state','newsletterbridge.send','newsletterbridge.archive'] as $action) $user->testRights['com_newsletterbridge:'.$action]=true;
$user->testRights['com_simplenewsletter:simplenewsletter.send']=true;
$app->loadIdentity($user);
$upstream=Dependency::factory(); check($app->getName()==='site','site application');
$original=$c->get(\Joomla\Database\DatabaseInterface::class);
$prefix='nbtest_'.bin2hex(random_bytes(6)).'_';
$options=['host'=>$app->get('host'),'user'=>$app->get('user'),'password'=>$app->get('password'),'database'=>$app->get('db'),'prefix'=>$prefix,'select'=>true];
$db=(new \Joomla\Database\DatabaseFactory())->getDriver($app->get('dbtype','mysqli'),$options);
$created=[];
$bridgeParams=\Joomla\CMS\Component\ComponentHelper::getParams('com_newsletterbridge');
$priorPresetCategory=$bridgeParams->get('preset_category',0);$bridgeParams->set('preset_category',0);
$mock=new class implements \Joomla\CMS\Mail\MailerFactoryInterface {
    public $messages=[];
    public function createMailer(?\Joomla\Registry\Registry $settings=null): \Joomla\CMS\Mail\MailerInterface {
        return new class($this) extends \Joomla\CMS\Mail\Mail {
            private $owner;
            public function __construct($owner) { parent::__construct(true); $this->owner=$owner; }
            public function Send() { $this->owner->messages[]=['body'=>$this->Body,'subject'=>$this->Subject,'recipients'=>$this->getToAddresses()]; return true; }
        };
    }
};
$child = new \Joomla\DI\Container($c);
$child->set(\Joomla\CMS\Mail\MailerFactoryInterface::class,$mock,true);
\Joomla\CMS\Factory::$container=$child;
$app->set('mailonline',1); $app->set('mailfrom','bridge@example.invalid');
// Prevent upstream finish() retention cleanup outside the isolated test prefix.
\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->set('queue_retention',30);
\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->set('sender_mode','global');
\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->set('batch_size',1);
try {
    foreach (['templates','jobs','queue','subscribers'] as $table) {
        $target=$prefix.'simplenewsletter_'.$table;
        $db->setQuery('CREATE TABLE '.$db->quoteName($target).' LIKE '.$db->quoteName($original->getPrefix().'simplenewsletter_'.$table))->execute();
        $created[]=$target;
    }
    $db->setQuery('CREATE TABLE '.$db->quoteName($prefix.'newsletterbridge_archive').' LIKE '.$db->quoteName($original->getPrefix().'newsletterbridge_archive'))->execute();
    $created[]=$prefix.'newsletterbridge_archive';
    $factory=new \Joomla\CMS\MVC\Factory\MVCFactory('SaintArt\\Component\\SimpleNewsletter');
    $factory->setDatabase($db); $factory->setDispatcher($c->get(\Joomla\Event\DispatcherInterface::class));
    $factory->setUserFactory($c->get(\Joomla\CMS\User\UserFactoryInterface::class));
    $adapter=new class($factory,$app,$db) {
        private $factory; private $app; private $db;
        public function __construct($factory,$app,$db) { $this->factory=$factory; $this->app=$app; $this->db=$db; }
        public function createModel($name,$prefix,$config) {
            $config['dbo']=$this->db;
            $m=$this->factory->createModel($name,$prefix,$config);
            $m->setDatabase($this->db);
            if (method_exists($m,'setCurrentUser')) $m->setCurrentUser($this->app->getIdentity());
            return $m;
        }
    };
    $bridge=new Bridge($app,$db,$adapter);
    $id=$bridge->save(0,['title'=>'Bridge integration','subject'=>'Hello {name}','body'=>'<p>Hello {name}. {email} <a href="{unsubscribe_link}">Unsubscribe</a></p>','published'=>0,'created_by'=>999]);
    $item=$bridge->item($id); check((int)$item->created_by===$user->id && (int)$item->published===0,'immutable owner and draft');
    $bridge->checkout($id); check((int)$bridge->item($id)->checked_out===$user->id,'upstream checkout in site');
    $user->id=900000002;$user->testRights['com_newsletterbridge:core.edit']=true;
    denied(fn()=>$bridge->checkout($id),409,'second user checkout locked');
    denied(fn()=>$bridge->checkin($id),409,'second user checkin locked');
    denied(fn()=>$bridge->save($id,['title'=>'No','subject'=>'No','body'=>'<p>No</p>']),409,'second user save locked');
    $user->id=900000001;unset($user->testRights['com_newsletterbridge:core.edit']);
    $bridge->save($id,['title'=>'Edited','subject'=>'Edited {name}','body'=>'<p>Edited {email}</p><img src="images/news.jpg"><a href="weekly/article.html">Article</a>','published'=>1]);
    check((int)$bridge->item($id)->checked_out===0 && (int)$bridge->item($id)->published===1,'save and upstream checkin');
    $bridge->changeState($id,0); check((int)$bridge->item($id)->published===0,'state endpoint');
    $bridge->changeState($id,1);
    // The synthetic user's configured HTML filter may strip every tag. Set an
    // isolated legacy-content fixture to test URLs independently of that policy.
    $fixture=(object)['id'=>$id,'body'=>'<p>Edited {email}</p><img src="images/news.jpg"><a href="weekly/article.html">Article</a>'];
    $db->updateObject('#__simplenewsletter_templates',$fixture,'id');
    $presets=$bridge->presets($id); check(count($presets)===6,'all upstream presets');
    denied(fn()=>$bridge->preset($id,'../hero-cta'),404,'preset traversal');
    check(str_contains($bridge->preview($id),'sample@example.invalid'),'sample preview');
    $bridge->test($id,'author@example.invalid'); check(count($mock->messages)===1,'test via mock transport');
    $siteRoot=\Joomla\CMS\Uri\Uri::root();
    check(str_contains($mock->messages[0]['body'],'src="'.$siteRoot.'images/news.jpg"'),'test mail absolute image: '.json_encode([$siteRoot,$mock->messages[0]['body']]));
    check(str_contains($bridge->preview($id),'href="'.$siteRoot.'weekly/article.html"'),'preview absolute article link');
    check(!str_contains($mock->messages[0]['body'],'token='),'test no subscriber token');
    // Artificial subscribers exist ONLY in the isolated empty test tables.
    foreach ([1,2,3] as $n) { $row=(object)['name'=>'Sample '.$n,'email'=>'sample'.$n.'@example.invalid','state'=>1,'token'=>str_repeat((string)$n,48)]; $db->insertObject('#__simplenewsletter_subscribers',$row,'id'); }
    $job=$bridge->start($id,true); check($job['total']===3,'upstream creates isolated queue');
    denied(fn()=>$bridge->start($id,true),409,'active job deduplication');
    $second=(new \Joomla\Database\DatabaseFactory())->getDriver($app->get('dbtype','mysqli'),$options);
    $lockName='newsletterbridge:'.substr(hash('sha256',$db->getPrefix()),0,40);
    $second->setQuery('SELECT GET_LOCK('.$second->quote($lockName).',0)')->loadResult();
    $mailBefore=count($mock->messages);
    try {denied(fn()=>$bridge->process($job['jobId']),409,'concurrent worker lock');check(count($mock->messages)===$mailBefore,'busy worker never mails');}
    finally {$second->setQuery('SELECT RELEASE_LOCK('.$second->quote($lockName).')')->loadResult();}
    $job=$bridge->process($job['jobId']); check($job['sent']===1 && !$job['done'],'first batch');
    check(str_contains($mock->messages[1]['body'],'src="'.$siteRoot.'images/news.jpg"'),'real upstream batch mock captures absolute image');
    // Emulate an older, partially sent job with a relative URL in its snapshot.
    $fixture=(object)['id'=>$job['jobId'],'body'=>'<img src="images/older.jpg">'];
    $db->updateObject('#__simplenewsletter_jobs',$fixture,'id');
    $db->setQuery('UPDATE #__simplenewsletter_jobs SET sent=0 WHERE id='.(int)$job['jobId'])->execute();
    $job=$bridge->process($job['jobId']); check($job['sent']===2 && !$job['done'],'resume recovers interrupted counters');
    check(str_contains($mock->messages[2]['body'],'src="'.$siteRoot.'images/older.jpg"'),'resume uses original snapshot with absolute URL, not edited template');
    check(\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->get('queue_retention')===30,'retention restored after batches');
    $job=$bridge->cancel($job['jobId']); check($job['state']===3 && $job['done'],'cancel');
    $before=count($mock->messages); $bridge->process($job['jobId']); check(count($mock->messages)===$before,'cancelled job never sends');
    $job2=$bridge->start($id,true);
    for($i=0;$i<3;$i++) $job2=$bridge->process($job2['jobId']);
    check($job2['done'] && $job2['sent']===3 && $job2['failed']===0,'complete batch job');
    check($bridge->jobs($id)['total']===2,'job history');

    $archive=new \SuperSoft\Component\Newsletterbridge\Site\Service\Archive($app,$db,$bridge);
    check($archive->listing()['total']===0,'archive private by default');
    denied(fn()=>$archive->draft($job['jobId']),409,'cancelled job cannot become public');
    $draft=$archive->draft($job2['jobId']);
    check($draft->revision===0 && !str_contains($draft->body,'{email}'),'snapshot anonymised before author preview');
    check($archive->editions($id)['total']===1,'only completed deliveries available');
    $public=['title'=>'Public issue','subject'=>'Public subject','body'=>'<p>Hello {name}. person@example.invalid <a href="{unsubscribe_link}">Leave</a></p>'];
    denied(fn()=>$archive->save($job2['jobId'],0,$public,true,false),400,'publication requires explicit review');
    unset($user->testRights['com_newsletterbridge:newsletterbridge.archive']);
    denied(fn()=>$archive->draft($job2['jobId']),403,'archive distinct permission');
    denied(fn()=>$archive->save($job2['jobId'],0,$public,true,true),403,'save checks archive grant');
    $user->testRights['com_newsletterbridge:newsletterbridge.archive']=true;
    $user->id=900000002;
    denied(fn()=>$archive->draft($job2['jobId']),403,'foreign archive draft denied');
    denied(fn()=>$archive->save($job2['jobId'],0,$public,true,true),403,'foreign publication denied');
    denied(fn()=>$archive->withdraw($job2['jobId'],0),403,'foreign withdrawal denied');
    $user->id=900000001;
    unset($user->testRights['com_simplenewsletter:simplenewsletter.send']);
    $archive->save($job2['jobId'],0,$public,false,false);
    check($archive->listing()['total']===0 && (int)$archive->draft($job2['jobId'])->revision===1,'private save without send rights');
    denied(fn()=>$archive->issue($job2['jobId']),404,'private issue hidden by direct URL');
    denied(fn()=>$archive->save($job2['jobId'],0,$public,true,true),409,'stale revision cannot overwrite');
    $archive->save($job2['jobId'],1,$public,true,true);
    check($archive->listing()['total']===1,'explicit approved copy visible');
    $published=$archive->issue($job2['jobId']);
    check(!str_contains($published->body,'person@example.invalid') && !str_contains($published->body,'href='),'public copy contains no email or personal link');
    check(array_keys((array)$published)===['job_id','title','subject','body','sent_at'],'public response allowlist');
    $user->id=0;
    check($archive->issue($job2['jobId'])->title==='Public issue','anonymous public reading');
    denied(fn()=>$archive->draft($job2['jobId']),403,'anonymous author endpoint denied');
    $user->id=900000001;
    $archive->withdraw($job2['jobId'],2);
    denied(fn()=>$archive->issue($job2['jobId']),404,'withdrawal immediate');
    denied(fn()=>$archive->withdraw($job2['jobId'],2),409,'stale withdrawal denied');
    $archive->save($job2['jobId'],3,$public,true,true);
    $archive->save($job2['jobId'],4,$public,false,false);
    check($archive->listing()['total']===0,'saving privately withdraws previous publication');
    $archive->save($job2['jobId'],5,$public,true,true);
    $db->setQuery('UPDATE #__simplenewsletter_jobs SET state=3 WHERE id='.(int)$job2['jobId'])->execute();
    denied(fn()=>$archive->issue($job2['jobId']),404,'upstream invalidation hides public copy');
    $user->testRights['com_simplenewsletter:simplenewsletter.send']=true;
    unset($user->testRights['com_simplenewsletter:simplenewsletter.send']);
    foreach (['status','process','cancel'] as $method) denied(fn()=>$bridge->$method($job2['jobId']),403,'dual ACL '.$method);
    $user->testRights['com_simplenewsletter:simplenewsletter.send']=true;
    $user->id=900000002;
    denied(fn()=>$bridge->item($id),403,'foreign resource denied');
    foreach (['status','process','cancel'] as $method) denied(fn()=>$bridge->$method($job2['jobId']),403,'foreign job '.$method);
    $user->id=900000001;
    $mvc=$app->bootComponent('com_newsletterbridge')->getMVCFactory();
    $model=$mvc->createModel('Newsletter','Site',['ignore_request'=>true]);
    $form=$model->getForm(['title'=>'Form','subject'=>'Form','body'=>'<p>Body</p>']);
    check($form && $form->getField('body')->type==='Editor','native Joomla editor form');
    check($model->validate($form,['title'=>'Form','subject'=>'Form','body'=>'<p>Body</p>','published'=>1])!==false,'native form validation');
    unset($user->testRights['com_newsletterbridge:core.edit.state']);
    $form=$model->getForm([],false); check($form->getField('published')===false,'state field removed by ACL');
    if (in_array('--email-providers', $argv, true)) { require __DIR__ . '/email-provider-joomla-cases.php'; }
    if (in_array('--administrator', $argv, true)) { require __DIR__ . '/admin-joomla-cases.php'; }
    echo 'PASS: '.$checks.' Joomla '.(new \Joomla\CMS\Version())->getShortVersion().' integration assertions; '.count($mock->messages).' messages captured in memory; zero real mail.'.PHP_EOL;
} finally {
    $bridgeParams->set('preset_category',$priorPresetCategory);
    foreach (array_reverse($created) as $table) { $db->setQuery('DROP TABLE '.$db->quoteName($table))->execute(); }
}
