<?php
include "taiga_config.php";

error_log('taiga notify start');
$a = json_decode(file_get_contents('php://input'),TRUE);
error_log('taiga notify ' . print_r($a,TRUE));
$info = getInfo($a);
$msg = "[Taiga] $info";
// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query(array('message'=>$msg)),
    ),
);
$context  = stream_context_create($options);
$result = file_get_contents($conf['announce_url'], false, $context);
error_log('taiga notify end');
echo "script ended";

function getInfo($a) {
  $did = array('create' => 'created', 'delete' => 'deleted', 'change' => 'changed');
  $b = "{$a['action']} {$a['type']}";
  $who = (isset($a['change']) ? $a['change']['user']['name'] : $a['data']['owner']['name']);
  $who = ($a['action'] == 'delete') ? "$who*" : $who;
  $whatname = ($a['type'] == 'wikipage') ? $a['data']['slug'] : $a['data']['subject'];
  $what = ucfirst($a['type']) . " '$whatname'";
  $how = $did[$a['action']];
  $msg = "$what $how by $who";
  return $msg;
}
