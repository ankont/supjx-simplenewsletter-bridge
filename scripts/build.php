<?php
/** Build the component ZIP with portable paths and a root manifest. */
$root = dirname(__DIR__);
$source = $root . '/source';
$manifest = simplexml_load_file($source . '/newsletterbridge.xml');
if (!$manifest) { throw new RuntimeException('Invalid manifest'); }
$version = (string) $manifest->version;
if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version)) { throw new RuntimeException('Invalid version'); }
$dist = $root . '/dist';
if (!is_dir($dist)) { mkdir($dist, 0777, true); }
$target = $dist . '/com_newsletterbridge-' . $version . '.zip';
if (is_file($target)) { unlink($target); }
$zip = new PharData($target, 0, null, Phar::ZIP);
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) { $files[] = $file->getPathname(); }
}
sort($files);
foreach ($files as $file) {
    $relative = str_replace('\\', '/', substr($file, strlen($source) + 1));
    $zip->addFile($file, $relative);
}
if (is_file($root . '/README.md')) { $zip->addFile($root . '/README.md', 'README.md'); }
unset($zip);
// Only obsolete bridge archives in this workspace's dist directory are removed.
foreach (glob($dist . '/com_newsletterbridge-*.zip') as $old) {
    if ($old !== $target && realpath(dirname($old)) === realpath($dist)) { unlink($old); }
}
echo $target . PHP_EOL;
