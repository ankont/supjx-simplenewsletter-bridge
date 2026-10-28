<?php
/** Included by the mock-protected real Joomla runner. No HTTP or real mail. */
$oldApp=\Joomla\CMS\Factory::$application;
$admin=$c->get(\Joomla\CMS\Application\AdministratorApplication::class);
$admin->loadIdentity($user); $admin->loadDocument(new \Joomla\CMS\Document\HtmlDocument()); $admin->loadLanguage($app->getLanguage());
try {
    \Joomla\CMS\Factory::$application=$admin;
    unset($user->testRights['com_newsletterbridge:core.manage']);
    denied(fn()=>new \SuperSoft\Component\Newsletterbridge\Site\Service\Bridge($admin,$db,$adapter),403,'backend requires manage');
    foreach(['Display','Newsletter','Send','Preset','Publication'] as $name){
        $controller=$mvc->createController($name,'Administrator',[], $admin,$admin->getInput());
        denied(fn()=>$controller->execute('unknown'),403,'backend '.$name.' denied before task execution');
    }
    $user->testRights['com_newsletterbridge:core.manage']=true;
    $user->testRights['com_newsletterbridge:core.edit.state']=true;
    $backend=new \SuperSoft\Component\Newsletterbridge\Site\Service\Bridge($admin,$db,$adapter);
    check($backend->item($id)->id==$id,'backend uses same newsletter ACL');
    $backendId=$backend->save(0,['title'=>'Backend fixture','subject'=>'Backend {name}','body'=>'<p>Backend {email}</p>','published'=>1]);
    check((int)$backend->item($backendId)->created_by===$user->id,'backend creation retains immutable ownership');
    $backend->checkout($backendId);
    $backend->save($backendId,['title'=>'Backend edited','subject'=>'Backend {name}','body'=>'<p>Edited backend {email}</p>','published'=>1]);
    check((int)$backend->item($backendId)->checked_out===0,'backend edit uses same checkout/checkin');
    foreach(['com_newsletterbridge:newsletterbridge.send','com_simplenewsletter:simplenewsletter.send'] as $grant){
        unset($user->testRights[$grant]);
        denied(fn()=>$backend->test($backendId,'author@example.invalid'),403,'backend still needs '.$grant);
        $user->testRights[$grant]=true;
    }
    $backendJob=$backend->start($backendId,true);
    check($backendJob['total']===3,'backend start uses same isolated queue');
    $backendJob=$backend->process($backendJob['jobId']);
    check($backendJob['sent']===1,'backend first batch uses same sender');
    $backendJob=$backend->process($backendJob['jobId']);
    check($backendJob['sent']===2,'backend resume uses same snapshot');
    $backend->cancel($backendJob['jobId']);
    $adminModel=$mvc->createModel('Newsletter','Administrator',['ignore_request'=>true]);
    $adminForm=$adminModel->getForm(['title'=>'Backend form','subject'=>'Backend','body'=>'<p>{name}</p>']);
    $fieldId=$adminForm->getField('body')->id;
    $options=$admin->getDocument()->getScriptOptions('plg_editorsxtd_sourcesbutton.preview');
    check($options['editors'][$fieldId]['presentation']==='email','backend real field ID declares email context');
    check($adminModel->validate($adminForm,['title'=>'Backend form','subject'=>'Backend','body'=>'<p>{name}</p>','published'=>1])!==false,'backend keeps native validation');
    $list=$mvc->createModel('Newsletters','Administrator',['ignore_request'=>true]);
    check($list instanceof \SuperSoft\Component\Newsletterbridge\Site\Model\NewslettersModel,'backend list shares model');
    foreach(['Newsletters','Newsletter','Preview','Send','Publications','Publication'] as $name){
        check($mvc->createView($name,'Administrator','Html',[])!==null,'backend '.$name.' view exists');
    }
    $admin->getInput()->set('id',0);
    $editorView=$mvc->createView('Newsletter','Administrator','Html',['base_path'=>JPATH_ADMINISTRATOR.'/components/com_newsletterbridge']);
    $editorView->setModel($adminModel,true);
    ob_start();
    try { $editorView->display(); $editorHtml=ob_get_contents(); }
    finally { ob_end_clean(); }
    check(str_contains($editorHtml,'id="'.$fieldId.'"') && str_contains($editorHtml,'data-nb-email-preview-status'),'backend native editor renders with explicit context notice');
    $admin->getInput()->set('view','newsletter');
    $display=$mvc->createController('Display','Administrator',['base_path'=>JPATH_ADMINISTRATOR.'/components/com_newsletterbridge'],$admin,$admin->getInput());
    ob_start();
    try { $display->execute('display'); $dispatchHtml=ob_get_contents(); }
    finally { ob_end_clean(); }
    check(str_contains($dispatchHtml,'data-nb-editor'),'backend controller renders shared editor through native MVC');
    $oldInput=$admin->getInput()->get('view'); $admin->getInput()->set('view','archive');
    $controller=$mvc->createController('Display','Administrator',[],$admin,$admin->getInput());
    denied(fn()=>$controller->execute('display'),404,'backend excludes public subscription views');
    $admin->getInput()->set('view',$oldInput);
    // Providers are still anonymous and preserve the snapshot in administrator context.
    $count=count($mock->messages); $backend->test($id,'author@example.invalid');
    check(count($mock->messages)===$count+1,'backend test uses same mock mail transport');
    check($admin->getIdentity()===$user,'backend identity remains original after preparation');
} finally {
    \Joomla\CMS\Factory::$application=$oldApp;
    unset($user->testRights['com_newsletterbridge:core.manage']);
}
