<?php
/** Standalone protocol/error/identity regressions. No extensions, mail or network. */
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication() { return self::$app; } } }
namespace Joomla\CMS\Plugin { class PluginHelper {
    public static $providers=[]; public static $imports=0;
    public static function getPlugin($group) { return self::$providers; }
    public static function importPlugin($group) { if($group!=='newsletter') throw new \LogicException('Wrong plugin group'); self::$imports++; }
} }
namespace Joomla\CMS\User { class User { public $id=0; } }
namespace Joomla\CMS\Uri { class Uri { public static function root() { return 'https://example.invalid/sub/'; } } }
namespace Joomla\Registry { class Registry {
    private $data;
    public function __construct($data) { $this->data=$data; }
    public function get($key) { $result=$this->data; foreach(explode('.',$key) as $part) $result=$result[$part]??null; return $result; }
    public function set($key,$value) { $ref=&$this->data; foreach(explode('.',$key) as $part) $ref=&$ref[$part]; $ref=$value; }
    public function toArray() { return $this->data; }
} }
namespace Joomla\Event { class Event {
    public function __construct(public $name,private $arguments) {}
    public function getArgument($key) { return $this->arguments[$key]??null; }
} }
namespace {
    define('_JEXEC',1);
    require dirname(__DIR__).'/source/site/src/Service/EmailPreparation.php';
    use SuperSoft\Component\Newsletterbridge\Site\Service\EmailPreparation as Prep;
    use Joomla\CMS\Plugin\PluginHelper;
    $checks=0;
    function check($ok,$message) { global $checks; $checks++; if(!$ok) throw new \RuntimeException($message); }
    function denied($work,$code) { try { $work(); } catch(\RuntimeException $e) { check($e->getCode()===$code,'error status'); return; } throw new \LogicException('Unexpected success'); }
    check(Prep::render('<p>Hello {name}</p>','preview')==='<p>Hello {name}</p>','no providers preserves ordinary email');
    check(PluginHelper::$imports===0,'no imports or app needed without providers');
    foreach(['{sources:{"version":2}}','{smartbrowser:bad}','<!-- {other:bad} -->','<a title="{future:bad}">x</a>','{unknown}'] as $body) denied(fn()=>Prep::render($body,'test'),400);
    Prep::assertPrepared('<style>.card{color:red}@media screen{.x{display:block}}</style>{email} {date}'); check(true,'CSS and standard placeholders accepted');
    denied(fn()=>Prep::assertPrepared('{sources:bad}',true),409);
    denied(fn()=>Prep::render(str_repeat('x',2000001),'preview'),400);
    denied(fn()=>Prep::render("\xff",'preview'),400);
    $app=new class {
        public $user; public $handler; public $calls=0;
        public function __construct() { $this->user=(object)['id'=>7]; }
        public function getIdentity() { return $this->user; }
        public function loadIdentity($user) { $this->user=$user; }
        public function isClient($client) { return $client==='site'; }
        public function getDispatcher() { return $this; }
        public function dispatch($name,$event) { $this->calls++; ($this->handler)($event); return $event; }
    };
    \Joomla\CMS\Factory::$app=$app; $identity=$app->user; PluginHelper::$providers=[(object)['name'=>'fixture']];
    foreach(['preview','test','send'] as $purpose) {
        $app->handler=function($event)use($app,$purpose){
            check($app->user->id===0,'anonymous identity in provider');
            check($event->name===Prep::EVENT && $event->getArgument('context')==='com_newsletterbridge.newsletter','event name/context');
            $doc=$event->getArgument('document');
            check($doc->get('purpose')===$purpose && $doc->get('publicOnly')===true && $doc->get('version')===1,'immutable context');
            $doc->set('html','<p>Rendered {name}</p>'); $doc->set('usage.items',1); $doc->set('usage.tokens',1);
        };
        check(Prep::render('{fixture:{}}',$purpose)==='<p>Rendered {name}</p>','provider output');
        check($app->user===$identity,'identity restored');
    }
    foreach (['preview','test','send'] as $purpose) {
        $app->handler=function($event) {
            $doc=$event->getArgument('document');
            $doc->set('html','<p>Large public selection</p>');
            $doc->set('usage.items',120); $doc->set('usage.tokens',16);
        };
        check(Prep::render('{fixture:{}}',$purpose)==='<p>Large public selection</p>','accurate counters above legacy caps accepted');
        check($app->user===$identity,'identity restored for large selection');
    }
    foreach([
        fn($d)=>$d->set('publicOnly',false), fn($d)=>$d->set('version',99),
        fn($d)=>$d->set('usage.items','61'), fn($d)=>$d->set('usage.tokens',-1),
        fn($d)=>$d->set('errors',['fixture.failure']), fn($d)=>$d->set('unresolved',['fixture']),
        fn($d)=>$d->set('html',[]), fn($d)=>$d->set('html',str_repeat('x',2000001)),
        fn($d)=>$d->set('extra','not part of the protocol'), fn($d)=>$d->set('usage.extra',1),
    ] as $index=>$change) {
        $app->handler=fn($event)=>$change($event->getArgument('document'));
        denied(fn()=>Prep::render('Body','test'),[503,503,400,400,503,400,503,400,503,503][$index]);
        check($app->user===$identity,'identity restored after bad provider result');
    }
    foreach (['items','tokens'] as $key) {
        foreach ([-1,1.5,'9',null,true] as $value) {
            $app->handler=fn($event)=>$event->getArgument('document')->set('usage.'.$key,$value);
            denied(fn()=>Prep::render('Body','preview'),400);
        }
    }
    $app->handler=function(){throw new \RuntimeException('private configuration');};
    try { Prep::render('Body','send'); } catch(\RuntimeException $e) { check($e->getMessage()==='COM_NEWSLETTERBRIDGE_EMAIL_PREPARE_FAILED' && $e->getPrevious()===null,'provider error is masked even in exception chains'); }
    check($app->user===$identity,'identity restored after provider exception');
    $app->handler=static function(){};
    denied(fn()=>Prep::render('{fixture:unhandled}','test'),400);
    echo "PASS: $checks email preparation protocol assertions; no mail.\n";
}
