<?php
$cases=['csrf','get','unknown','display','id','body-array','name-array','subject-control','no-category','anonymous','foreign'];
foreach($cases as $case){
    $process=proc_open([PHP_BINARY,'/tmp/newsletterbridge-preset-controller-case.php',$case],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $json=json_decode(trim($out,"\xef\xbb\xbf \r\n\t"),true);
    if($exit!==0||$err!==''||!$json||$json['success']!==false||$json['data']!==null)throw new RuntimeException('FAIL '.$case.' '.$out.' '.$err);
}
echo 'PASS: '.count($cases).' actual preset controller cases; no mail calls.'.PHP_EOL;
