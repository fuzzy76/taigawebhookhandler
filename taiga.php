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
  static $statusverb = array(
    'New' => 'reset to new',
    'Ready' => 'made ready',
    'In progress' => 'started',
    'Ready for test' => 'made available for testing',
    'Done' => 'finished',
    'Archived' => 'archived',
    'Needs info' => 'requested info for',
    'Closed' => 'closed',
    'Rejected' => 'rejected',
    'Postponed' => 'postponed'
  );


  public function __construct($json) {
    $this->event = json_decode($json, TRUE);
    $this->objectname = ($this->event['type'] == 'wikipage') ? $this->event['data']['slug'] : $this->event['data']['subject'];
    $this->type = SELF::$typename[$this->event['type']];
    $this->action_did = SELF::$verb[$this->event['action']];
    $this->expandChanges();
  }

  function getInfo() {

    $what = ucfirst($this->type) . " '{$this->objectname}'";

    $how = $this->action_did;

    $who = (isset($this->event['change']) ? $this->event['change']['user']['name'] : $this->event['data']['owner']['name']);
    $who = ($this->event['action'] == 'delete') ? "$who*" : $who;

    $msg = "$what $how by $who";
    return $msg;
  }

  function expandChanges() {
    if ($this->event['action'] == 'change') {
      $diff = $this->event['change']['diff'];
      if ( (count($diff) == 1) && (isset($diff['status'])) ) {
        $status_to = $this->event['change']['values']['status'][ $diff['status']['to'] ];
        $this->action_did = $this->statusverb[$status_to];
      } else {
        $elems = implode(',', array_keys($diff));
        $this->action_did = "edited ($elems)";
      }
    }
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
