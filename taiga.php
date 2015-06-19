<?php
include "taiga_config.php";

$taigaevent = file_get_contents('php://input');
error_log("taiga notify $taigaevent");
$taigaevent = new TaigaEvent($taigaevent);
$info = $taigaevent->getInfo();
$msg = "[Taiga] $info";
post($conf['announce_url'], http_build_query(array('message'=>$msg)));

class TaigaEvent { // http://taigaio.github.io/taiga-doc/dist/webhooks.html
  // Source event
  public $event = NULL;

  // Calculated fields
  public $objectname = '';
  public $type = '';

  // Lookup tables
  static $verb = array(
    'create' => 'created',
    'delete' => 'deleted',
    'change' => 'changed'
  );
  static $typename = array(
    'test' => 'Testevent',
    'milestone' => 'sprint',
    'userstory' => 'user story',
    'task' => 'task',
    'issue' => 'issue',
    'wikipage' => 'wiki page'
  );


  public function __construct($json) {
    $this->event = json_decode($json, TRUE);
    $this->objectname = ($this->event['type'] == 'wikipage') ? $this->event['data']['slug'] : $this->event['data']['subject'];
    $this->type = SELF::$typename[$this->event['type']];
    $this->expandChanges();
  }

  function getInfo() {

    $what = ucfirst($this->type) . " '{$this->objectname}'";

    $how = SELF::$verb[$this->event['action']];

    $who = (isset($this->event['change']) ? $this->event['change']['user']['name'] : $this->event['data']['owner']['name']);
    $who = ($this->event['action'] == 'delete') ? "$who*" : $who;

    $msg = "$what $how by $who";
    return $msg;
  }

  function expandChanges() {
    // TODO parse changed events to rename events to "closed", "opened". etc.
  }
}

function post ($url, $body) {
  $options = array(
      'http' => array( // use key 'http' even if you send the request to https://...
          'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
          'method'  => 'POST',
          'content' => $body,
      ),
  );
  $result = file_get_contents($url, false, stream_context_create($options));
}
