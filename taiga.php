<?php

include "taiga_config.php";
$taigawh = new TaigaWebhook();
dpm($taigawh->data, 'json');
foreach ($taigawh->events as $event) {
  dpm($event->getDescription(), 'event');
  // @todo different output handlers (that's where most of the filtering will happen)
  if (!in_array($event->action, array('cheesedoodles'))) {
    post( $conf['announce_url'], http_build_query( array( 'message' => "[Taiga] ".$event->getDescription() ) ) );
  }
}

function dpm($var, $msg = 'debug') {
  $date = date(DATE_ATOM);
  $var = print_r($var,TRUE);
  $string = "[$date] $msg $var";
  global $conf;
  if (isset($conf['logfile']) && (php_sapi_name() != 'cli')) {
    error_log($string . "\n", 3, $conf['logfile']);
  } else if ((php_sapi_name() != 'cli')) {
    error_log($string);
  } else {
    echo "$string\n";
  }
}

class TaigaWebhook {
  public $json = '';
  public $data = NULL;
  public $events = array();
  public function __construct($json = NULL) {
    if (empty($json)) {
      if (!($json = file_get_contents('php://input'))) {
        if (!($json = file_get_contents('php://stdin'))) {
          // Oops. :p
        }
      }
    }
    $this->json = $json;
    $this->data = json_decode($json);
    $this->parseEvents();
    //dpm($this);
  }

  public function parseEvents() {
    if ($this->data->action != 'change') {
      $this->events[] = new TaigaEvent($this->data->action, $this->data->type, $this->getUserName(), $this->getObjectname());
    } else {
      foreach ($this->data->change->diff as $diffi => $diffv) {
        $action = $this->data->action;
        $type = $this->data->type;
        $objectname = $this->getObjectname();
        $username = $this->getUserName();
        $extra = array($diffi => $diffv->to);
        switch ($diffi) {
          case 'assigned_to':
            $action = 'assigned_to';
            $extra = array($diffi => $this->data->data->assigned_to->name);
            break;
          case 'description_html':
          case 'content_html':
          case 'taskboard_order':
          case 'finish_date':
            $action = NULL;
            break;
          case 'status':
            $action = 'statuschange';
            $extra = array($diffi => $this->data->change->values->status[ $diffv->to ]);
          default:
            break;
        }
        if ($action) {
          $event = new TaigaEvent($action, $type, $username, $objectname, $extra);
          $this->events[] = $event;
        }
      }
    }
  }

  public function getUserName() {
    if (isset($this->data->change->user->name))
      return $this->data->change->user->name;
    if (isset($this->data->data->owner->name))
      return $this->data->data->owner->name;
    return 'N/A';
  }
  public function getObjectname() {
    return ($this->event->type == 'wikipage') ? $this->data->data->slug : $this->data->data->subject;
  }
}

class TaigaEvent {
  public $action, $type, $user, $objectname, $extra;

  public function __construct($action, $type, $user, $objectname, $extra = NULL) {
    $this->action = $action;
    $this->type = $type;
    $this->user = $user;
    $this->objectname = $objectname;

    if ($action == 'change') {
      foreach($extra as &$val) {
        if (strlen($val) > 20) {
          $val = "[...]";
        }
      }
    }
    $this->extra = $extra;
  }

  public function getDescription() {
    $what = ucfirst($this->getTypeName()) . " '{$this->objectname}'"; // Task 'do something clever'
    $how = $this->getAction();
    $who = $this->user;
    $who = ($this->action == 'delete') ? "$who*" : $who;
    return "$what $how by $who";
  }

  function getTypeName() {
    static $typename = array('milestone' => 'sprint','userstory' => 'user story','wikipage' => 'wiki page');
    return (isset($typename[$this->type]) ? $typename[$this->type] : $this->type);
  }

  function getAction() {
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

    $out = 'undefined action ' . $this->action;
    if (isset($verb[$this->action])) {
      $out = $verb[$this->action];
//      }
      if ($this->action == 'change') {
        foreach ($this->extra as $i => $v) {
          $out .= " ($i=$v)";
        }
      }
    }

    if ($this->action == 'statuschange') {
      if (isset($statusverb[$this->extra])) {
        $out = $statusverb[$this->extra];
      }
      else {
        $out = 'changed status to "' . $this->extra . '"';
      }
    } else if ($this->action == 'assigned_to') {
      $out = "assigned to {$this->extra}";
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
