<?php
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication(){return self::$app;} } }
namespace {
define('_JEXEC',1);
require dirname(__DIR__).'/source/site/src/Service/EditorPresentation.php';
use SuperSoft\Component\Newsletterbridge\Site\Service\EditorPresentation;
$checks=0;
function check($ok){global $checks;$checks++;if(!$ok)throw new \RuntimeException('Editor context regression');}
$doc=new class {
    public $type='html'; public $options=['plg_editorsxtd_sourcesbutton.preview'=>['mode'=>'render','editors'=>['article'=>['presentation'=>'site']]]]; public $assets=[];
    public function getType(){return $this->type;}
    public function addScriptOptions($key,$value){$this->options[$key]=array_replace_recursive($this->options[$key]??[],$value);}
    public function getWebAssetManager(){return $this;}
    public function registerAndUseScript($name,$uri,...$args){$this->assets[$name]=$uri;return $this;}
};
$app=new class($doc){public function __construct(public $document){} public function getDocument(){return $this->document;}};
\Joomla\CMS\Factory::$app=$app;
foreach(['actual_first_body','actual_second_body','actual_first_body'] as $id){
    $form=new class($id){public function __construct(private $id){} public function getField($name){return (object)['id'=>$this->id];}};
    EditorPresentation::configure($form);
    check($doc->options['plg_editorsxtd_sourcesbutton.preview']['editors'][$id]['presentation']==='email');
}
check($doc->options['plg_editorsxtd_sourcesbutton.preview']['editors']['article']['presentation']==='site');
check($doc->options['plg_editorsxtd_sourcesbutton.preview']['mode']==='render');
check(count($doc->assets)===1);
check(count($doc->options['com_newsletterbridge.editorPresentation']['editors'])===2);
$before=$doc->options; $doc->type='json'; EditorPresentation::configure($form);check($before===$doc->options);
$app->document=null; EditorPresentation::configure($form);check(true);
echo "PASS: $checks editor context declarations; no content/editor filters changed.\n";
}
