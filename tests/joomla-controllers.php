<?php
/** Every child bootstraps the real SiteApplication and installs a mock before dispatch. */
$cases=[];
foreach(['start','test','process','resume','cancel','status','preset','display','getModel'] as $task)foreach(['0','1'] as $csrf)$cases[]=[$task,$csrf,'POST'];
$cases[]=['preset','1','GET'];
foreach($cases as $case) {
    $process=proc_open(array_merge([PHP_BINARY,'/tmp/newsletterbridge-controller-case.php'],$case),[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    $json=json_decode(trim($output,"\xef\xbb\xbf \r\n\t"),true);
    if($code!==0||$error!==''||!$json||$json['success']!==false||!is_string($json['message'])||$json['data']!==null)throw new RuntimeException('FAIL '.implode(' ',$case).' '.$output.' '.$error);
}
echo 'PASS: '.count($cases).' actual Joomla controller dispatch cases; no mailer reached, no real mail.'.PHP_EOL;
