<?php
$root = dirname(__DIR__);
$source = $root . '/source';
$checks = 0;
function check($ok, $what) { global $checks; $checks++; if (!$ok) throw new RuntimeException($what); }
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile()) continue;
    $files[] = $file->getPathname();
    if ($file->getExtension() === 'xml') { check(simplexml_load_file($file->getPathname()) !== false, 'Invalid XML: ' . $file); }
    if (in_array($file->getExtension(), ['php','xml','js'])) {
        $text = file_get_contents($file->getPathname());
        preg_match_all('/COM_NEWSLETTERBRIDGE_[A-Z_0-9]+/', $text, $matches);
        $keys = array_merge($keys ?? [], $matches[0]);
    }
}
$dynamic = ['COM_NEWSLETTERBRIDGE_JOB_STATE_0','COM_NEWSLETTERBRIDGE_JOB_STATE_1','COM_NEWSLETTERBRIDGE_JOB_STATE_2','COM_NEWSLETTERBRIDGE_JOB_STATE_3'];
$keys = array_unique(array_merge(array_filter($keys, fn ($key) => !str_ends_with($key, '_')), $dynamic));
foreach (['site','admin'] as $client) {
    foreach (['en-GB','el-GR'] as $locale) {
        $ini = parse_ini_file($source . '/' . $client . '/language/' . $locale . '/com_newsletterbridge.ini', false, INI_SCANNER_RAW);
        foreach ($keys as $key) { check(isset($ini[$key]), 'Missing ' . $locale . ' key: ' . $key); }
        $sys = parse_ini_file($source . '/' . $client . '/language/' . $locale . '/com_newsletterbridge.sys.ini', false, INI_SCANNER_RAW);
        check(isset($sys['COM_NEWSLETTERBRIDGE_MENU_TITLE'], $sys['COM_NEWSLETTERBRIDGE_DESCRIPTION']), 'Missing sys.ini');
    }
}
$m = simplexml_load_file($source . '/newsletterbridge.xml');
check((string) $m->creationDate !== '' && (string) $m->version === '1.2.0', 'Manifest metadata');
foreach ([$m->files, $m->administration->files] as $group) {
    foreach ($group->children() as $entry) check(file_exists($source . '/' . $group['folder'] . '/' . (string) $entry), 'Missing manifest file');
}
foreach ([$m->languages, $m->administration->languages] as $group) {
    foreach ($group->language as $entry) check(is_file($source . '/' . $group['folder'] . '/' . (string) $entry), 'Missing manifest language');
}
$archives = glob($root . '/dist/com_newsletterbridge-*.zip');
check(count($archives) === 1, 'Exactly one bridge ZIP required');
$zip = new PharData($archives[0]);
check(isset($zip['newsletterbridge.xml']), 'Manifest must be in ZIP root');
foreach (new RecursiveIteratorIterator($zip) as $entry) {
    $name = str_replace('phar://' . str_replace('\\', '/', $archives[0]) . '/', '', str_replace('\\', '/', $entry->getPathname()));
    check(!str_contains($name, '../') && !str_starts_with($name, 'source/'), 'Unsafe archive entry');
}
// Read raw central-directory filenames, not PHP's path normalisation.
$binary = file_get_contents($archives[0]); $offset = 0;
while (($offset = strpos($binary, "PK\x01\x02", $offset)) !== false) {
    $length = unpack('v', substr($binary, $offset + 28, 2))[1];
    $name = substr($binary, $offset + 46, $length);
    check(!str_contains($name, '\\'), 'Windows separator inside ZIP');
    $offset += 46 + $length;
}
echo 'PASS: ' . $checks . " package assertions.\n";
