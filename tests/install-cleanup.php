<?php
/** Upgrade cleanup must remove only the two obsolete component files. */
define('_JEXEC', 1);
$root=sys_get_temp_dir().'/newsletterbridge-cleanup-'.bin2hex(random_bytes(6));
define('JPATH_ROOT',$root);
require dirname(__DIR__).'/source/script.php';
$files=[
    $root.'/components/com_newsletterbridge/src/Service/SourcesEmail.php',
    $root.'/components/com_newsletterbridge/layouts/email/sources.php',
    $root.'/components/com_newsletterbridge/src/Service/EmailPreparation.php',
    $root.'/templates/test/html/layouts/com_newsletterbridge/email/sources.php',
];
$dirs=[]; $checks=0;
function check($ok) { global $checks; $checks++; if(!$ok) throw new RuntimeException('Unsafe installer cleanup'); }
try {
    foreach($files as $path) {
        for($dir=dirname($path);!is_dir($dir);$dir=dirname($dir)) $dirs[$dir]=true;
        if(!is_dir(dirname($path))) mkdir(dirname($path),0755,true);
        file_put_contents($path,'fixture');
    }
    $script=new Com_NewsletterbridgeInstallerScript();
    check($script->postflight('update',null));
    check(!is_file($files[0]) && !is_file($files[1]));
    check(file_get_contents($files[2])==='fixture' && file_get_contents($files[3])==='fixture');
    check($script->postflight('update',null));
    file_put_contents($files[0],'fixture');
    check($script->postflight('uninstall',null) && is_file($files[0]));
} finally {
    foreach($files as $path) if(is_file($path)) unlink($path);
    $paths=array_keys($dirs); usort($paths,static fn($a,$b)=>strlen($b)<=>strlen($a));
    foreach($paths as $path) if(is_dir($path) && count(scandir($path))===2) rmdir($path);
}
echo "PASS: $checks installer cleanup assertions; unrelated files and overrides preserved.\n";
