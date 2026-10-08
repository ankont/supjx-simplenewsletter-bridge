<?php
/** Standalone security/regression tests. No Joomla site, network, DB or real mail. */
namespace Joomla\CMS {
    class Factory {
        public static $container;
        public static function getDate() { return new class { public function toSql() { return '2026-10-08 00:00:00'; } }; }
        public static function getContainer() { return self::$container; }
    }
}
namespace Joomla\CMS\Component { class ComponentHelper {
    public static function filterText($s) { return preg_replace('~<script.*?</script>~is', '', $s); }
    public static $params;
    public static function getParams($name) { return self::$params ??= new class { private $values=[]; public function get($key,$default) { return $this->values[$key] ?? $default; } public function set($key,$value) { $this->values[$key]=$value; } }; }
} }
namespace Joomla\CMS\Mail { interface MailerFactoryInterface {} }
namespace SaintArt\Component\SimpleNewsletter\Administrator\Helper {
    class NewsletterHelper {
        public static function replaceTags($body, $data) { return strtr($body, ['{name}' => $data['name'], '{email}' => $data['email'], '{unsubscribe_link}' => $data['token']]); }
        public static function getSender() { return ['sender@example.invalid', 'Sample sender']; }
        public static function getReplyTo() { return ''; }
    }
    class PresetHelper {
        public static function getPresets() { return [['id' => 'simple-letter', 'title' => 'Simple letter', 'subject' => 'Sample']]; }
        public static function getBody($id) { return '<p>Sample</p>'; }
    }
}
namespace {
    define('_JEXEC', 1);
    $base = dirname(__DIR__) . '/source/site/src/Service/';
    foreach (['Policy','InputGuard','Bridge'] as $class) { require $base . $class . '.php'; }
    use SuperSoft\Component\Newsletterbridge\Site\Service\Policy;
    use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
    use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
    $checks = 0;
    function ok($condition, $name) { global $checks; $checks++; if (!$condition) { throw new \RuntimeException('FAIL: ' . $name); } }
    function fails(callable $work, int $code, string $name) {
        try { $work(); } catch (\RuntimeException $e) { ok($e->getCode() === $code, $name . ' code'); return; }
        throw new \RuntimeException('FAIL (accepted): ' . $name);
    }
    class User {
        public $id; public $permissions;
        public function __construct($id, $permissions) { $this->id = $id; $this->permissions = array_fill_keys($permissions, true); }
        public function authorise($action, $asset) { return isset($this->permissions[$asset . ':' . $action]); }
    }
    $actions = ['newsletterbridge.access','core.create','core.edit','core.edit.own','core.edit.state','newsletterbridge.send'];
    for ($mask = 0; $mask < 128; $mask++) {
        $perms = [];
        foreach ($actions as $bit => $action) { if ($mask & (1 << $bit)) $perms[] = 'com_newsletterbridge:' . $action; }
        if ($mask & 64) $perms[] = 'com_simplenewsletter:simplenewsletter.send';
        $policy = new Policy(new User(7, $perms));
        $access = (bool) ($mask & 1); $all = (bool) ($mask & 4); $own = (bool) ($mask & 8);
        $send = (bool) ($mask & 32) && (bool) ($mask & 64);
        foreach ([7, 8, 0] as $owner) {
            $item = (object) ['id' => 10, 'created_by' => $owner];
            $expected = $access && ($all || ($own && $owner === 7));
            ok($policy->edit($item) === $expected, 'ACL edit matrix');
            ok($policy->read($item) === $expected, 'ACL read matrix');
            ok($policy->send($item) === ($expected && $send), 'ACL dual send matrix');
            foreach ([7,8,0] as $jobOwner) {
                $job = (object) ['template_id' => 10, 'created_by' => $jobOwner];
                ok($policy->job($job, $item) === ($expected && $send && ($all || $jobOwner === 7)), 'job ownership matrix');
                $job->template_id = 99; ok(!$policy->job($job, $item), 'job binding');
            }
        }
        $guest = new Policy(new User(0, $perms)); ok(!$guest->enter(), 'guest always denied');
    }
    foreach (['https://evil.test','//evil.test','javascript:alert(1)',"index.php?option=com_newsletterbridge&view=newsletters\r\nX: y",'index.php?option=com_newsletterbridge&view=newsletters&task=send.start','index.php?option=com_newsletterbridge&view=newsletters&Itemid=1%0d%0a','index.php?option=com_newsletterbridge&view=newsletters#//evil.test'] as $url) {
        ok(InputGuard::returnUrl($url, 'fallback') === 'fallback', 'hostile return URL');
    }
    $return = 'index.php?option=com_newsletterbridge&view=newsletters&Itemid=22';
    ok(InputGuard::returnUrl($return, 'fallback') === $return, 'canonical return');
    foreach (['-1','1 OR 1=1','1.0','01','2147483648',[],null,''] as $id) fails(fn () => InputGuard::id($id), 400, 'invalid id');
    ok(InputGuard::id('12') === 12 && InputGuard::id('0', true) === 0, 'valid ids');
    foreach ([-2, 2, 'false', [], null] as $state) fails(fn () => InputGuard::state($state), 400, 'invalid state');
    $valid = ['title' => 'Hello','subject' => 'News','body' => '<p>Hello {name}</p>'];
    foreach ([['title' => ''], ['subject' => "Bad\r\nBcc:x"], ['body' => '<p></p>'], ['title' => str_repeat('a',256)], ['body' => []]] as $bad) fails(fn () => InputGuard::newsletter(array_replace($valid,$bad)), 400, 'invalid fields');
    ok(InputGuard::newsletter(array_replace($valid,['title'=>str_repeat('α',255)]))['title']===str_repeat('α',255),'Greek character length');
    fails(fn()=>InputGuard::newsletter(array_replace($valid,['title'=>str_repeat('α',256)])),400,'Greek title limit');
    ok(array_keys(InputGuard::newsletter($valid + ['created_by' => 99,'id' => 23])) === ['title','subject','body'], 'metadata discarded');

    class Query {
        public $parts = [];
        public function __call($name, $args) { $this->parts[] = $name . ':' . json_encode($args); return $this; }
        public function __toString() { return implode(' ', $this->parts); }
    }
    class DB {
        public $item; public $job; public $query; public $writes = []; public $lock = 1; public $active = false; public $queueSent = 1;
        public function __construct() {
            $this->item = (object) ['id'=>10,'created_by'=>7,'title'=>'Title','subject'=>'Hi {name}','body'=>'<p>{email} {unsubscribe_link}</p>','published'=>1,'checked_out'=>7];
            $this->job = (object) ['id'=>20,'template_id'=>10,'created_by'=>7,'state'=>1,'total'=>3,'sent'=>1,'failed'=>0,'created'=>'now','finished'=>null];
        }
        public function getQuery($new) { return new Query(); }
        public function quoteName($name, $alias = null) { return $name; }
        public function quote($s) { return "'" . str_replace("'", "''", $s) . "'"; }
        public function getPrefix() { return 'test_'; }
        public function setQuery($q, $start = 0, $limit = 0) { $this->query = (string) $q; return $this; }
        public function loadObject() { return str_contains($this->query, 'simplenewsletter_templates') ? clone $this->item : clone $this->job; }
        public function loadObjectList() { return [(object)['state'=>0,'row_count'=>3-$this->queueSent],(object)['state'=>1,'row_count'=>$this->queueSent]]; }
        public function loadResult() {
            if (str_contains($this->query, 'GET_LOCK')) return $this->lock;
            if (str_contains($this->query, 'RELEASE_LOCK')) return 1;
            return $this->active ? 20 : 0;
        }
        public function transactionStart() { $this->writes[] = 'begin'; }
        public function transactionCommit() { $this->writes[] = 'commit'; }
        public function transactionRollback() { $this->writes[] = 'rollback'; }
        public function updateObject($table, $row, $key) { $this->writes[] = ['table'=>$table,'row'=>$row]; if($table==='#__simplenewsletter_jobs'){foreach((array)$row as $k=>$v)$this->job->$k=$v;} }
    }
    class App {
        private $user;
        public $online = 1;
        public function __construct($user) { $this->user = $user; }
        public function getIdentity() { return $this->user; }
        public function get($key, $default) { return $this->online; }
    }
    class Table {
        public $id = 10; public $data;
        public function load($id) { $this->id = $id; return true; }
        public function bind($data) { $this->data = $data; return true; }
        public function check() { return true; }
        public function store() { $this->id = $this->data['id'] ?: 11; return true; }
    }
    class TemplateModel {
        public $table; public $checked = [];
        public function __construct() { $this->table = new Table(); }
        public function getTable() { return $this->table; }
        public function checkout($id) { $this->checked[] = ['out',$id]; return true; }
        public function checkin($id) { $this->checked[] = ['in',$id]; return true; }
    }
    class Sender {
        public $calls = []; private $db;
        public function __construct($db) { $this->db=$db; }
        public function processBatch($id,$limit) { ok(\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->get('queue_retention',30)===0, 'global queue purge suppressed'); $this->calls[] = ['process',$id,$limit]; $this->db->job->sent++; $this->db->queueSent++; }
        public function createJob($id) { $this->calls[] = ['start',$id]; return clone $this->db->job; }
        public function cancelJob($id) { $this->calls[] = ['cancel',$id]; $this->db->job->state = 3; }
    }
    class MVCFactory {
        public $sender; public $template;
        public function __construct($db) { $this->sender = new Sender($db); $this->template = new TemplateModel(); }
        public function createModel($name,$prefix,$config) { ok($prefix === 'Administrator' && $config['ignore_request'] === true, 'explicit upstream MVC context'); return $name === 'Send' ? $this->sender : $this->template; }
    }
    $rights = ['com_newsletterbridge:newsletterbridge.access','com_newsletterbridge:core.create','com_newsletterbridge:core.edit.own','com_newsletterbridge:newsletterbridge.send','com_simplenewsletter:simplenewsletter.send'];
    $db = new DB(); $app = new App(new User(7,$rights)); $mvc = new MVCFactory($db); $bridge = new Bridge($app,$db,$mvc);
    ok(array_keys($bridge->status(20)) === ['jobId','templateId','state','total','sent','failed','done'], 'status exact safe fields');
    ok(!str_contains($db->query, 'email') && !str_contains($db->query, 'token'), 'no subscriber query for status');
    $db->job->created_by = 8;
    foreach (['status','process','cancel'] as $method) fails(fn () => $bridge->$method(20), 403, 'foreign job ' . $method);
    ok(!$mvc->sender->calls, 'foreign job never executes');
    $db->job->created_by = 7; $db->item->created_by = 8;
    foreach (['status','process','cancel'] as $method) fails(fn () => $bridge->$method(20), 403, 'foreign newsletter ' . $method);
    fails(fn () => $bridge->test(10,'test@example.invalid'), 403, 'foreign test denied');
    $db->item->created_by = 7;
    $db->lock = 0; fails(fn () => $bridge->process(20), 409, 'concurrent batch rejected');
    ok(!$mvc->sender->calls, 'busy never executes'); $db->lock = 1;
    $bridge->process(20); ok($mvc->sender->calls === [['process',20,25]], 'process delegates bounded batch');
    ok(str_contains($db->query, 'RELEASE_LOCK'), 'batch lock released');
    ok(\Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter')->get('queue_retention',30)===30, 'retention config restored');
    $db->job->sent=0; $db->queueSent=2; $bridge->process(20); ok($db->job->sent===3,'resume recovers counters from recorded queue');
    $processedCount=count($mvc->sender->calls);
    $db->job->state = 2; $bridge->process(20); ok(count($mvc->sender->calls) === $processedCount, 'finished batch no resend');
    $db->job->state = 3; $bridge->process(20); ok(count($mvc->sender->calls) === $processedCount, 'cancelled batch no resend');
    $db->job->state = 1; $bridge->cancel(20); ok($db->job->state === 3, 'cancel delegates');
    fails(fn () => $bridge->start(10,false), 400, 'explicit send confirmation');
    $db->item->published = 0; fails(fn () => $bridge->start(10,true), 409, 'unpublished start rejected');
    $db->item->published = 1; $db->active = true; fails(fn () => $bridge->start(10,true), 409, 'duplicate active job');
    $db->active = false; $bridge->start(10,true); ok(in_array(['start',10],$mvc->sender->calls,true), 'start delegates');
    fails(fn () => $bridge->save(10,$valid + ['published' => 0]), 403, 'state escalation rejected');
    $bridge->save(10,$valid + ['created_by' => 99,'created' => 'fake','checked_out' => 99,'id' => 77]);
    ok(!isset($mvc->template->table->data['created_by']) && $mvc->template->table->data['id'] === 10, 'existing ownership and id protected');
    $bridge->save(0,$valid + ['created_by' => 99,'published' => 0]);
    ok($mvc->template->table->data['created_by'] === 7 && $mvc->template->table->data['published'] === 0, 'new owner assigned and draft default');
    $db->item->checked_out = 8;
    fails(fn () => $bridge->checkout(10),409,'foreign checkout');
    fails(fn () => $bridge->checkin(10),409,'foreign checkin');
    fails(fn () => $bridge->save(10,$valid),409,'foreign lock save');
    fails(fn () => $bridge->start(10,true),409,'foreign lock start');
    $db->item->checked_out = 0; fails(fn () => $bridge->save(10,$valid),409,'save requires checkout');
    $db->item->checked_out = 7; $bridge->checkout(10); $bridge->checkin(10);
    fails(fn () => $bridge->preset(10,'../simple-letter'),404,'preset traversal rejected');
    ok($bridge->preset(10,'simple-letter')['body'] === '<p>Sample</p>', 'preset allowlist');
    ok($bridge->preview(10) === '<p>sample@example.invalid </p>', 'preview generic no token');
    class Mail {
        public $body; public $recipient;
        public function __call($name,$args) { if ($name==='setBody')$this->body=$args[0]; if($name==='addRecipient')$this->recipient=$args[0]; }
        public function Send() { return true; }
    }
    $mail = new Mail();
    \Joomla\CMS\Factory::$container = new class($mail) {
        private $mail;
        public function __construct($mail) { $this->mail=$mail; }
        public function get($key) { return $this; }
        public function createMailer() { return $this->mail; }
    };
    $bridge->test(10,'author@example.invalid');
    ok($mail->body === '<p>sample@example.invalid </p>' && $mail->recipient === 'author@example.invalid', 'test mail sample-only mock transport');
    $app->online = 0; fails(fn () => $bridge->test(10,'author@example.invalid'),503,'mail disabled');
    fails(fn () => $bridge->process(20),503,'batch mail disabled');
    foreach (['newsletterbridge.send','simplenewsletter.send'] as $missing) {
        $perms = array_values(array_filter($rights, fn ($p) => !str_ends_with($p, ':' . $missing)));
        $b = new Bridge(new App(new User(7,$perms)),$db,$mvc);
        foreach (['status','process','cancel'] as $method) fails(fn () => $b->$method(20),403,'missing send grant ' . $method);
    }
    echo 'PASS: ' . $checks . " assertions; mock transport only, no external state changed.\n";
}
