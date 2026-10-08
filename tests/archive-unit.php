<?php
define('_JEXEC', 1);
require dirname(__DIR__) . '/source/site/src/Service/PublicContent.php';
require dirname(__DIR__) . '/source/site/src/Service/Policy.php';
use SuperSoft\Component\Newsletterbridge\Site\Service\PublicContent as Content;
use SuperSoft\Component\Newsletterbridge\Site\Service\Policy;
$checks = 0;
function check($ok, $label) { global $checks; $checks++; if (!$ok) { throw new RuntimeException($label); } }
$good = Content::html('<p>Hello <strong>{name}</strong>, welcome.</p><img src="/images/news.jpg" alt="News">', 'Συνδρομητή');
check(str_contains($good, 'Hello <strong>Συνδρομητή</strong>, welcome.'), 'preserve whitespace and Greek replacement');
check(str_contains($good, 'src="/images/news.jpg"'), 'local static image preserved');
$bad = '<script>alert(1)</script><style>body{color:red}</style><svg onload="alert(1)"><a>bad</a></svg><iframe src="https://evil.test"></iframe><form><input name="token" value="secret"></form><!-- secret -->';
check(Content::html($bad) === '', 'active elements and comments discarded');
foreach (['javascript:alert(1)','data:text/html,secret','//evil.test/path','https://site.test/?token=secret','/confirm/secret','/unsubscribe/secret','/path#secret','/path%3Ftoken%3Dsecret','mailto:person@example.test','https://person:secret@site.test/path',"https:\\evil.test"] as $url) {
    $clean = Content::html('<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" onclick="alert(1)">Link</a>');
    check(!str_contains($clean, 'href=') && !str_contains($clean, 'onclick'), 'unsafe/personal URL: ' . $url);
}
foreach (['https://site.test/page','/about','images/news.html'] as $url) { check(str_contains(Content::html('<a href="'.$url.'">Link</a>'), 'href='), 'ordinary link allowed'); }
check(!str_contains(Content::html('<img src="https://tracker.test/pixel.png"><img src="/images/pixel.png" width="1">'), '<img'), 'no remote images or one pixel image');
$copy = Content::html('<p title="{name} person@example.test">Hello {name}, {email}, {token}.<a href="{unsubscribe_link}">Leave</a></p>');
foreach (['person@example.test','{name}','{email}','{token}','{unsubscribe_link}','href='] as $secret) check(!str_contains($copy, $secret), 'personal data removed ' . $secret);
check(str_contains($copy, 'Subscriber'), 'generic name');
$css = Content::html('<div id="secret" data-token="secret" style="color:red;background:url(https://evil.test);position:fixed;width:100%;font-family:Arial" onmouseover="x()">Text</div>');
check(str_contains($css, 'color:red') && str_contains($css,'width:100%'), 'layout styles preserved');
foreach (['url(', 'position', 'data-token', 'onmouseover', 'id='] as $bad) check(!str_contains($css, $bad), 'style/attribute stripped '.$bad);
foreach (['<p><b>Text</p></b>', '</body><script>alert(1)</script><p>Safe</p>', '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(1)>">', '<a href="jav&#x61;script:alert(1)">x</a>'] as $html) {
    $clean = Content::html($html);
    check(!preg_match('/<(script|style|math)|onerror=|href="javascript/i', $clean), 'malformed HTML safe');
    check(Content::html($clean) === $clean, 'sanitizer idempotent');
}
check(Content::text('Hello {name} person@example.test') === 'Hello Subscriber [email]', 'plain text anonymisation');
$user = new class { public $id=1; public $rights=[]; function authorise($action,$asset) { return !empty($this->rights[$action]); } };
$policy = new Policy($user); $own=(object)['id'=>1,'created_by'=>1];$foreign=(object)['id'=>2,'created_by'=>2];
check(!$policy->archive($own), 'archive default denied');
$user->rights=['newsletterbridge.access'=>true,'core.edit.own'=>true,'newsletterbridge.archive'=>true];
check($policy->archive($own), 'author own archive'); check(!$policy->archive($foreign), 'foreign archive denied');
unset($user->rights['newsletterbridge.archive']);check(!$policy->archive($own), 'separate archive grant');
$user->rights['newsletterbridge.archive']=true;$user->rights['core.edit']=true;check($policy->archive($foreign), 'edit-all archive');
$user->id=0;check(!$policy->archive($own), 'guest cannot manage archive');
echo 'PASS: ' . $checks . ' archive assertions; no mail or database access.' . PHP_EOL;
