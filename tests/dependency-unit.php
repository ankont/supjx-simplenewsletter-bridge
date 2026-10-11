<?php
namespace Joomla\CMS { class Factory { public static $db; public static $app; public static function getContainer(){return new class {public function get($id){return Factory::$db;}};}public static function getApplication(){return self::$app;}}class Version {const MAJOR_VERSION=6;public static $version='6.1.3';public function getShortVersion(){return self::$version;}} }
namespace Joomla\CMS\Component {class ComponentHelper {public static $enabled=true;public static function isEnabled($name){return self::$enabled;}}}
namespace SaintArt\Component\SimpleNewsletter\Administrator\Helper {class PresetHelper{public static function getPresets(){}public static function getBody(){}}class NewsletterHelper{public static function replaceTags(){}public static function getSender(){}public static function getReplyTo(){}}}
namespace {
 define('_JEXEC',1);$temp=sys_get_temp_dir().'/nb-compat-'.bin2hex(random_bytes(6));define('JPATH_ADMINISTRATOR',$temp);
 mkdir($temp.'/components/com_newsletterbridge',0777,true);mkdir($temp.'/components/com_simplenewsletter/src',0777,true);
 $file=$temp.'/components/com_simplenewsletter/src/sample.php';file_put_contents($file,'sample');
 file_put_contents($temp.'/components/com_newsletterbridge/compatibility.json',json_encode(['version'=>'1.3.0','sha256'=>['src/sample.php'=>hash('sha256','sample')]]));
 require dirname(__DIR__).'/source/site/src/Service/Dependency.php';
 use SuperSoft\Component\Newsletterbridge\Site\Service\Dependency;
 class Query {public $parts=[];public function __call($name,$args){$this->parts[]=$name;return $this;}public function __toString(){return implode(' ',$this->parts);}}
 class DB {public $version='1.3.0';public $missing=false;public $engine='InnoDB';public $query;
 public function getQuery($new){return new Query;}public function quoteName($name){return $name;}public function quote($value){return "'".$value."'";}public function setQuery($q){$this->query=(string)$q;return $this;}public function replacePrefix($s){return str_replace('#__','nb_',$s);}
 public function loadResult(){return str_contains($this->query,'ENGINE')?$this->engine:json_encode(['version'=>$this->version]);}
 public function getTableColumns($name,$type){return array_fill_keys($this->missing?['id']:['id','title','subject','body','published','created_by','created','modified','checked_out','checked_out_time','template_id','state','total','sent','failed','finished','job_id','subscriber_id','email','name','token','error','sent_date'],true);}}
 class Send {public function createJob(){}public function processBatch(){}public function getPendingCount(){}public function getJob(){}public function cancelJob(){}}
 class Template {public function getTable(){}public function checkout(){}public function checkin(){}}
 class MVC {public $broken=false;public function createModel($name,$prefix,$config){if($this->broken)return new \stdClass;return $name==='Send'?new Send:new Template;}}
 $db=new DB;$mvc=new MVC;\Joomla\CMS\Factory::$db=$db;\Joomla\CMS\Factory::$app=new class($mvc){private $mvc;function __construct($mvc){$this->mvc=$mvc;}function bootComponent($name){return new class($this->mvc){private $mvc;function __construct($mvc){$this->mvc=$mvc;}function getMVCFactory(){return $this->mvc;}};}function getLanguage(){return new class{function load(...$args){return true;}};}};
 $checks=0;function check($ok,$label){global $checks;$checks++;if(!$ok)throw new \RuntimeException('FAIL '.$label);}
 function resetFactory(){(new \ReflectionProperty(Dependency::class,'factory'))->setValue(null,null);}
 function deny($key){resetFactory();try{Dependency::factory();throw new \LogicException('Accepted missing capability');}catch(\RuntimeException $e){check($e->getCode()===503&&$e->getMessage()===$key,$key);}}
 try {
  check(Dependency::factory()===$mvc,'baseline factory');check(Dependency::warnings()===[],'baseline no warnings');
  foreach(['9.0.0','',null] as $version){$db->version=$version;resetFactory();check(Dependency::factory()===$mvc,'unverified version remains usable');check(in_array('COM_NEWSLETTERBRIDGE_COMPAT_VERSION_WARNING',Dependency::warnings(),true),'admin version warning');}
  $db->version='1.3.0';file_put_contents($file,'changed');resetFactory();check(Dependency::factory()===$mvc,'changed file does not block');check(Dependency::warnings()===['COM_NEWSLETTERBRIDGE_COMPAT_FILES_WARNING'],'admin hash warning');
  $db->missing=true;deny('COM_NEWSLETTERBRIDGE_DEPENDENCY_SCHEMA');$db->missing=false;
  $db->engine='MyISAM';deny('COM_NEWSLETTERBRIDGE_DEPENDENCY_STORAGE');$db->engine='InnoDB';
  $mvc->broken=true;deny('COM_NEWSLETTERBRIDGE_DEPENDENCY_API');$mvc->broken=false;
  \Joomla\CMS\Component\ComponentHelper::$enabled=false;deny('COM_NEWSLETTERBRIDGE_DEPENDENCY_MISSING');\Joomla\CMS\Component\ComponentHelper::$enabled=true;
  \Joomla\CMS\Version::$version='5.3.0';deny('COM_NEWSLETTERBRIDGE_DEPENDENCY_JOOMLA');
  echo 'PASS: '.$checks.' compatibility assertions; no live database or mail.'.PHP_EOL;
 }finally{unlink($file);unlink($temp.'/components/com_newsletterbridge/compatibility.json');rmdir(dirname($file));rmdir($temp.'/components/com_simplenewsletter');rmdir($temp.'/components/com_newsletterbridge');rmdir($temp.'/components');rmdir($temp);}
}