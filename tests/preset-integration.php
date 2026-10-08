<?php
/** Authorised local integration: temporary category/articles, mandatory mock mailer, cleanup in finally. */
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
use SuperSoft\Component\Newsletterbridge\Site\Service\PresetLibrary;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
use Joomla\CMS\Component\ComponentHelper;
$checks=0;
function check($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
function denied($work,$code,$label){try{$work();}catch(RuntimeException $e){check($e->getCode()===$code,$label.' '.$e->getMessage());return;}throw new RuntimeException('FAIL accepted '.$label);}
class PresetTestUser extends \Joomla\CMS\User\User {
    public $rights=[]; public $levels=[1,2];
    public function authorise($action,$assetname=null){
        if(isset($this->rights[$assetname.':'.$action]))return true;
        if(str_starts_with((string)$assetname,'com_content.article.')) {
            global $db;$id=(int)substr($assetname,20);$catid=(int)$db->setQuery('SELECT catid FROM #__content WHERE id='.$id)->loadResult();
            return isset($this->rights['com_content.category.'.$catid.':'.$action]);
        }
        return false;
    }
    public function getAuthorisedViewLevels(){return $this->levels;}
}
$user=new PresetTestUser();$user->id=900000003;$user->guest=0;$user->groups=[2];$user->name='Preset synthetic author';
foreach(['newsletterbridge.access','core.create','core.edit.own'] as $right)$user->rights['com_newsletterbridge:'.$right]=true;
$app->loadIdentity($user);$app->getInput()->set('option','com_newsletterbridge');
$child=new \Joomla\DI\Container($c);
$mail=new class implements \Joomla\CMS\Mail\MailerFactoryInterface {
    public $calls=0;
    public function createMailer(?\Joomla\Registry\Registry $settings=null):\Joomla\CMS\Mail\MailerInterface{$this->calls++;throw new RuntimeException('Mock: mail prohibited');}
};
$child->set(\Joomla\CMS\Mail\MailerFactoryInterface::class,$mail,true);\Joomla\CMS\Factory::$container=$child;
$category=new \Joomla\CMS\Table\Category($db);$articles=[];$newsletter=0;$categoryId=0;
$params=ComponentHelper::getParams('com_newsletterbridge');$oldCategory=$params->get('preset_category',0);
$oldSaveHistory=ComponentHelper::getParams('com_content')->get('save_history',1);
ComponentHelper::getParams('com_content')->set('save_history',0);
try {
    $category->setLocation(1,'last-child');
    $category->bind(['title'=>'Bridge preset test '.bin2hex(random_bytes(4)),'alias'=>'nb-preset-test-'.bin2hex(random_bytes(6)),
        'extension'=>'com_content','published'=>0,'access'=>2,'language'=>'*','params'=>'{}','metadata'=>'{}']);
    if(!$category->check()||!$category->store())throw new RuntimeException('Cannot create category');$categoryId=(int)$category->id;
    $asset='com_content.category.'.$categoryId;
    $user->rights[$asset.':core.create']=true;$user->rights[$asset.':core.edit.own']=true;
    $params->set('preset_category',$categoryId);
    $library=new PresetLibrary($app,$db);
    check(count($library->choices())===6,'six built-ins initially');
    check($library->canSave(),'can create in category');
    $new=$library->save('Reusable test','Subject {name}','<p>Saved {name} {unsubscribe_link}</p>');
    $id=(int)substr($new['preset'],8);$articles[]=$id;
    check(count($new['presets'])===7,'new article in unified list');
    $row=$db->setQuery('SELECT * FROM #__content WHERE id='.$id)->loadObject();
    check((int)$row->catid===$categoryId && (int)$row->created_by===$user->id,'category and owner controlled');
    check((int)$row->state===0 && (int)$row->access===2 && (int)$row->checked_out===0,'unpublished restricted and checked in');
    check((int)$row->asset_id>0,'native Joomla asset');
    check((int)$db->setQuery('SELECT COUNT(*) FROM #__workflow_associations WHERE item_id='.$id.' AND extension='.$db->quote('com_content.article'))->loadResult()===1,'native workflow association');
    check($library->get($new['preset'])['subject']==='Subject {name}','subject round trip');
    check(str_contains($library->get($new['preset'])['body'],'{unsubscribe_link}'),'placeholders preserved');
    $updated=$library->save(' reusable   TEST ','Updated subject','<p>Updated body</p>');
    check($updated['preset']===$new['preset'] && count($updated['presets'])===7,'same normalised name updates one article');
    check((int)$db->setQuery('SELECT COUNT(*) FROM #__content WHERE catid='.$categoryId)->loadResult()===1,'no duplicate insert');
    check(trim(strip_tags($library->get($new['preset'])['body']))==='Updated body' && $library->get($new['preset'])['subject']==='Updated subject','updated content used');
    $english=parse_ini_file(JPATH_SITE.'/language/en-GB/com_newsletterbridge.ini',false,INI_SCANNER_RAW);
    $greek=parse_ini_file(JPATH_SITE.'/language/el-GR/com_newsletterbridge.ini',false,INI_SCANNER_RAW);
    $builtIn=$library->save($english['COM_NEWSLETTERBRIDGE_PRESET_SIMPLE_LETTER'],'English override','<p>Custom original replacement</p>');
    $override=(int)$db->setQuery('SELECT article_id FROM #__newsletterbridge_presets WHERE builtin_id='.$db->quote('simple-letter').' AND article_id IN (SELECT id FROM #__content WHERE catid='.$categoryId.')')->loadResult();$articles[]=$override;
    check($builtIn['preset']==='simple-letter' && count($builtIn['presets'])===7,'built-in shadowed without extra option');
    check(trim(strip_tags($library->get('simple-letter')['body']))==='Custom original replacement','built-in loads article');
    $library->save($greek['COM_NEWSLETTERBRIDGE_PRESET_SIMPLE_LETTER'],'Greek override','<p>Greek override body</p>');
    check((int)$db->setQuery('SELECT COUNT(*) FROM #__content WHERE catid='.$categoryId)->loadResult()===2,'translated name updates same override');
    check($library->get('simple-letter')['subject']==='Greek override','translated override subject');

    check(\SuperSoft\Component\Newsletterbridge\Site\Service\PresetNames::key(' ΑΠΛΉ   ΕΠΙΣΤΟΛΉ ')
        === \SuperSoft\Component\Newsletterbridge\Site\Service\PresetNames::key('Απλή επιστολή'),'Greek case and whitespace matching');
    check(\SuperSoft\Component\Newsletterbridge\Site\Service\PresetNames::key("\xc2\xa0Reusable\xc2\xa0") === 'reusable','Unicode edge whitespace matching');
    $failFactory=new class($app) {
        private $app;public function __construct($app){$this->app=$app;}
        public function createModel($name,$prefix,$config){
            $real=$this->app->bootComponent('com_content')->getMVCFactory()->createModel($name,$prefix,$config);
            return new class($real) {
                private $model;public function __construct($model){$this->model=$model;}
                public function __call($name,$args){return $this->model->$name(...$args);}
                public function save($data){$this->model->save($data);return false;}
            };
        }
    };
    $failing=new PresetLibrary($app,$db,$categoryId,$failFactory);
    denied(fn()=>$failing->save('reusable TEST','Should rollback','<p>Should rollback</p>'),500,'model failure');
    check($library->get($new['preset'])['subject']==='Updated subject' && trim(strip_tags($library->get($new['preset'])['body']))==='Updated body','failed save rolls back body and subject');
    check((int)$db->setQuery('SELECT checked_out FROM #__content WHERE id='.$id)->loadResult()===0,'failed save rolls back checkout');
    denied(fn()=>$failing->save('Failure new preset','Should not exist','<p>No partial article</p>'),500,'failed first save');
    check((int)$db->setQuery('SELECT COUNT(*) FROM #__content WHERE catid='.$categoryId.' AND title='.$db->quote('Failure new preset'))->loadResult()===0,'failed first save removes partial article');
    $second=new \Joomla\Database\DatabaseFactory();
    $other=$second->getDriver($app->get('dbtype','mysqli'),['host'=>$app->get('host'),'user'=>$app->get('user'),'password'=>$app->get('password'),'database'=>$app->get('db'),'prefix'=>$db->getPrefix(),'select'=>true]);
    $lock='nb:preset:'.substr(hash('sha256',$db->getPrefix().':'.$categoryId),0,40);
    $other->setQuery('SELECT GET_LOCK('.$other->quote($lock).',0)')->loadResult();
    try { denied(fn()=>$library->save('reusable TEST','Concurrent','<p>Concurrent</p>'),409,'concurrent category lock'); }
    finally { $other->setQuery('SELECT RELEASE_LOCK('.$other->quote($lock).')')->loadResult(); }
    $duplicate=$app->bootComponent('com_content')->getMVCFactory()->createTable('Article','Administrator');
    $duplicate->setCurrentUser($user);$duplicate->bind(['title'=>'reusable test','alias'=>'duplicate-'.bin2hex(random_bytes(4)),'catid'=>$categoryId,'state'=>0,'access'=>2,'created_by'=>$user->id,'language'=>'*','introtext'=>'Duplicate']);
    if(!$duplicate->check()||!$duplicate->store())throw new RuntimeException('Cannot create duplicate fixture');
    denied(fn()=>$library->save('reusable TEST','Ambiguous','<p>Ambiguous</p>'),409,'manual duplicate names rejected');
    $duplicate->delete((int)$duplicate->id);
    unset($user->rights[$asset.':core.create']);
    check($library->canSave(),'can update existing without create');
    denied(fn()=>$library->save('Forbidden new','Subject','<p>Body</p>'),403,'new requires category create');
    $user->id=900000004;
    check(count($library->choices())===6,'foreign draft hidden, built-in available');
    denied(fn()=>$library->get($new['preset']),404,'foreign article direct id hidden');
    $user->rights[$asset.':core.create']=true;
    denied(fn()=>$library->save('reusable TEST','Hijack','<p>Hijack</p>'),403,'same-name foreign article not overwritten');
    denied(fn()=>$library->save($greek['COM_NEWSLETTERBRIDGE_PRESET_SIMPLE_LETTER'],'Hijack','<p>Hijack</p>'),403,'foreign built-in override not overwritten');
    $user->id=900000003;
    $db->setQuery('UPDATE #__content SET checked_out=900000004 WHERE id='.$id)->execute();
    denied(fn()=>$library->save('reusable TEST','Locked','<p>Locked</p>'),409,'foreign checkout protected');
    $db->setQuery('UPDATE #__content SET checked_out=900000003 WHERE id='.$id)->execute();
    denied(fn()=>$library->save('reusable TEST','Locked','<p>Locked</p>'),409,'same-user other-tab checkout protected');
    $db->setQuery('UPDATE #__content SET checked_out=0,access=3 WHERE id='.$id)->execute();
    denied(fn()=>$library->get($new['preset']),404,'view level enforced');
    denied(fn()=>$library->save('reusable TEST','Restricted','<p>Restricted</p>'),403,'restricted same-name article protected');
    $db->setQuery('UPDATE #__content SET access=2 WHERE id='.$id)->execute();
    $db->setQuery('UPDATE #__content SET state=-2 WHERE id='.$override)->execute();
    check(!str_contains($library->get('simple-letter')['body'],'Greek override body'),'trash restores original');
    $db->setQuery('UPDATE #__content SET state=0,title='.$db->quote('Renamed independent preset').' WHERE id='.$override)->execute();
    check(count($library->choices())===8,'external rename detaches built-in override');
    check(!str_contains($library->get('simple-letter')['body'],'Greek override body'),'rename restores original');
    $db->setQuery('UPDATE #__content SET introtext='.$db->quote('<p>Edited with Joomla</p>').' WHERE id='.$id)->execute();
    check(trim(strip_tags($library->get($new['preset'])['body']))==='Edited with Joomla' && $library->get($new['preset'])['subject']==='Updated subject','external article edit keeps newsletter subject');
    $bridge=new Bridge();$newsletter=$bridge->save(0,['title'=>'Preset integration newsletter','subject'=>'Subject','body'=>'<p>Body</p>']);
    $user->id=900000004;
    denied(fn()=>$bridge->savePreset($newsletter,['title'=>'No','subject'=>'No','body'=>'<p>No</p>']),403,'newsletter resource ACL for save preset');
    unset($user->rights['com_newsletterbridge:core.create']);
    denied(fn()=>$bridge->savePreset(0,['title'=>'No','subject'=>'No','body'=>'<p>No</p>']),403,'new newsletter context ACL');
    $user->id=900000003;$user->rights['com_newsletterbridge:core.create']=true;
    check($mail->calls===0,'no mail factory called');
    echo 'PASS: '.$checks.' real Joomla article preset assertions; zero mail calls.'.PHP_EOL;
} finally {
    $user->id=900000003;$params->set('preset_category',$oldCategory);ComponentHelper::getParams('com_content')->set('save_history',$oldSaveHistory);
    if($newsletter)$db->setQuery('DELETE FROM #__simplenewsletter_templates WHERE id='.$newsletter.' AND created_by=900000003')->execute();
    if($categoryId) {
        // Includes any row saved just before a failing assertion.
        $ids=$db->setQuery('SELECT id FROM #__content WHERE catid='.$categoryId)->loadColumn();
        foreach($ids as $id){
            $db->setQuery('DELETE FROM #__newsletterbridge_presets WHERE article_id='.(int)$id)->execute();
            $db->setQuery('DELETE FROM #__workflow_associations WHERE item_id='.(int)$id.' AND extension='.$db->quote('com_content.article'))->execute();
            $table=$app->bootComponent('com_content')->getMVCFactory()->createTable('Article','Administrator');$table->load((int)$id);$table->delete((int)$id);
        }
        $category->delete($categoryId);
    }
}
