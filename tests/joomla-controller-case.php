<?php
ob_start();require '/tmp/newsletterbridge-inspect.php';ob_end_clean();
$state=json_decode(file_get_contents('/tmp/newsletterbridge-e2e-state.json'),true);
$author=$c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($state['author']['id']);$app->loadIdentity($author);
$child=new \Joomla\DI\Container($c);
$mock=new class implements \Joomla\CMS\Mail\MailerFactoryInterface {
    public $calls=0;
    public function createMailer(?\Joomla\Registry\Registry $settings=null): \Joomla\CMS\Mail\MailerInterface {
        $this->calls++;throw new RuntimeException('Mock transport: no real mail permitted');
    }
};
$child->set(\Joomla\CMS\Mail\MailerFactoryInterface::class,$mock,true);\Joomla\CMS\Factory::$container=$child;
register_shutdown_function(function() use($mock){if($mock->calls)fwrite(STDERR,'ACL TEST REACHED MOCK MAILER');});
$input=$app->getInput();
$itemId=(int)$db->setQuery('SELECT id FROM #__simplenewsletter_templates WHERE created_by = '.(int)$author->id.' ORDER BY id ASC')->loadResult();
if(!$itemId)throw new RuntimeException('Create author browser fixture first.');
$token=\Joomla\CMS\Session\Session::getFormToken();$input->post->set($token,($argv[2]??'0')==='1'?'1':null);
$input->post->set('id',(string)$itemId);$input->post->set('job_id','2147483647');$input->post->set('confirmed','1');
$input->post->set('email','never-send@example.invalid');$input->post->set('preset','../hero-cta');
$input->server->set('REQUEST_METHOD',$argv[3]??'POST');
$factory=$app->bootComponent('com_newsletterbridge')->getMVCFactory();
$factory->createController('Send','Site',[],$app,$input)->execute($argv[1]??'preset');
