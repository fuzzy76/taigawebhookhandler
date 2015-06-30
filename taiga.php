<?php
include "taiga_config.php";

$taigaevent = file_get_contents('php://input');
dpm($taigaevent, 'incoming json');
$taigaevent = new TaigaEvent($taigaevent);
post( $conf['announce_url'], http_build_query( array( 'message' => "[Taiga] ".$taigaevent->getInfo() ) ) );

function dpm($var, $msg = '') {
  $string = ( isset($msg) ? "$msg " : '' ) . print_r($var);
  global $conf;
  if (isset($conf['logfile'])) {
    error_log($string . "\n", 3, $conf['logfile']);
  } else {
    error_log($string);
  }
}

class TaigaEvent { // http://taigaio.github.io/taiga-doc/dist/webhooks.html

  public $event = NULL;
  // Calculated fields
  public $objectname = '';
  public $action = 'nop';
  public $extra = NULL;

  public function __construct($json) {
    $this->event = json_decode($json, TRUE);
    $this->objectname = ($this->event['type'] == 'wikipage') ? $this->event['data']['slug'] : $this->event['data']['subject'];
    $this->action = $this->event['action'];
    $this->expandChanges(); // Will overwrite action and action_did if necessary
  }

  function getTypeName() {
    static $typename = array('milestone' => 'sprint','userstory' => 'user story','wikipage' => 'wiki page');
    return (isset($typename[$this->event['type']]) ? $typename[$this->event['type']] : $this->event['type']);
  }

  function getInfo() {
    $what = ucfirst($this->getTypeName()) . " '{$this->objectname}'";
    $how = $this->createDescription($this->action);
    $who = (isset($this->event['change']) ? $this->event['change']['user']['name'] : $this->event['data']['owner']['name']);
    $who = ($this->action == 'delete') ? "$who*" : $who;
    return "$what $how by $who";
  }

  function expandChanges() {
    if ($this->action == 'change') {
      $diff = $this->event['change']['diff'];
      if ( isset($diff['status']) ) {
        // Status change, lookup new status
        // NB! This ignore other changes
        $status_to = $this->event['change']['values']['status'][ $diff['status']['to'] ];
        $this->action = 'statuschange';
        $this->extra = $status_to;
        //$this->extra = (isset($this->event['data']['status']) ? $this->event['data']['status'] : NULL);
      } else {
        // General changes, list fields
        $this->extra = array_keys($diff);
      }
    }
  }
  function createDescription($in) {
    static $verb = array(
      'create' => 'created',
      'delete' => 'deleted',
      'change' => 'changed',
    );
    static $statusverb = array(
//      'New' => 'reset to new',
//      'Ready' => 'made ready',
      'In progress' => 'started',
//      'Ready for test' => 'made available for testing',
      'Done' => 'finished',
      'Archived' => 'archived',
//      'Needs info' => 'requested info for',
      'Closed' => 'closed',
      'Rejected' => 'rejected',
      'Postponed' => 'postponed'
    );

    $out = 'undefined action '.$in;
    if (isset($verb[$in])) {
      $out = $verb[$in];
      if ($in == 'change' && is_array($this->extra)) {
        $out .= " (".implode(',',$this->extra).")";
      }
    }

    if ($in == 'statuschange') {
      if (isset($statusverb[$this->extra])) {
        $out = $statusverb[$this->extra];
      } else {
        $out = 'changed status to "'.$this->extra.'"';
      }
    }
    return $out;
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
