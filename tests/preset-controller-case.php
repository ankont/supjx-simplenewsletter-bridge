<?php
/** Negative dispatch only; a mandatory mock protects any unexpected mail path. */
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$fixture=json_decode(file_get_contents('/tmp/newsletterbridge-e2e-state.json'),true);
$case=$argv[1]??'csrf';$role=$case==='foreign'?'sender':'author';
$app->loadIdentity($c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($fixture[$role]['id']));
$child=new \Joomla\DI\Container($c);$mock=new class implements \Joomla\CMS\Mail\MailerFactoryInterface {
    public $calls=0;public function createMailer(?\Joomla\Registry\Registry $settings=null):\Joomla\CMS\Mail\MailerInterface{$this->calls++;throw new RuntimeException('Mock: mail prohibited');}
};
$child->set(\Joomla\CMS\Mail\MailerFactoryInterface::class,$mock,true);\Joomla\CMS\Factory::$container=$child;
$input=$app->getInput();$input->set('option','com_newsletterbridge');
$input->server->set('REQUEST_METHOD',$case==='get'?'GET':'POST');
$input->post->set(\Joomla\CMS\Session\Session::getFormToken(),$case==='csrf'?null:'1');
$input->post->set('id','0');$input->post->set('name','');$input->post->set('subject','Never send');$input->post->set('body','<p>Never send</p>');
$task='save';
switch($case){
    case 'foreign':$input->post->set('name','Απλή επιστολή');break;
    case 'unknown':$task='getModel';break;
    case 'display':$task='display';break;
    case 'id':$input->post->set('id','1 OR 1=1');break;
    case 'body-array':$input->post->set('name','Must not save');$input->post->set('body',[]);break;
    case 'name-array':$input->post->set('name',[]);break;
    case 'subject-control':$input->post->set('name','Must not save');$input->post->set('subject',"Subject\r\nBcc:invalid");break;
    case 'no-category':\Joomla\CMS\Component\ComponentHelper::getParams('com_newsletterbridge')->set('preset_category',0);$input->post->set('name','Must not save');break;
    case 'anonymous':$app->loadIdentity(new \Joomla\CMS\User\User());$input->post->set('name','Must not save');break;
}
register_shutdown_function(function()use($mock){if($mock->calls)fwrite(STDERR,'Unexpected mock mailer call');});
$app->bootComponent('com_newsletterbridge')->getMVCFactory()->createController('Preset','Site',[],$app,$input)->execute($task);
