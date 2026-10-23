<?php
/** Standalone URL regression tests: no mail, network or database. */
define('_JEXEC', 1);
require dirname(__DIR__) . '/source/site/src/Service/EmailUrls.php';
use SuperSoft\Component\Newsletterbridge\Site\Service\EmailUrls;
$checks = 0;
function same($actual, $expected): void {
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException("Expected: $expected\nActual: $actual"); }
}
$root = 'https://example.test:8443/joomla/';
foreach ([
    'images/a.jpg' => 'https://example.test:8443/joomla/images/a.jpg',
    '/images/a.jpg' => 'https://example.test:8443/images/a.jpg',
    '../images/a.jpg' => 'https://example.test:8443/images/a.jpg',
    './article.html?a=1&b=2#part' => 'https://example.test:8443/joomla/article.html?a=1&b=2#part',
    '//cdn.test/a.jpg' => 'https://cdn.test/a.jpg',
    '?a=1' => 'https://example.test:8443/joomla/?a=1',
    '#part' => '#part', 'mailto:a@example.test' => 'mailto:a@example.test',
    'tel:123' => 'tel:123', 'cid:image1' => 'cid:image1',
    'data:image/png;base64,AAAA' => 'data:image/png;base64,AAAA',
    'https://other.test/a.jpg' => 'https://other.test/a.jpg',
    '{siteurl}images/a.jpg' => '{siteurl}images/a.jpg', '' => '',
] as $url => $expected) { same(EmailUrls::absolute($url, $root), $expected); }
$html = '<p>Greek text unchanged</p><a href="article?a=1&amp;b=2">Link</a><img src=images/a.jpg><img srcset="images/a.jpg 1x, /images/b.jpg 2x"><td background="images/bg.jpg" style="background-image:url(images/bg.jpg)">X</td>';
$expected = '<p>Greek text unchanged</p><a href="https://example.test:8443/joomla/article?a=1&amp;b=2">Link</a><img src="https://example.test:8443/joomla/images/a.jpg"><img srcset="https://example.test:8443/joomla/images/a.jpg 1x, https://example.test:8443/images/b.jpg 2x"><td background="https://example.test:8443/joomla/images/bg.jpg" style="background-image:url(https://example.test:8443/joomla/images/bg.jpg)">X</td>';
same(EmailUrls::html($html, $root), $expected);
same(EmailUrls::html($expected, $root), $expected);
same(EmailUrls::html('<img src="cid:image1"><a href="#part">x</a>', $root), '<img src="cid:image1"><a href="#part">x</a>');
same(EmailUrls::html('<img srcset="data:image/png;base64,AAAA 1x">', $root), '<img srcset="data:image/png;base64,AAAA 1x">');
echo "PASS: $checks email URL assertions; no real mail.\n";
