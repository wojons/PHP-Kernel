<?php

require_once(dirname(__FILE__).'/event.php');

class task {
    
    public $waitFor    = null;
    public $proxyValue = array();
    public $super      = array();
    
    private $state;
    private $post_run = array();
    private $task_id  = null;
    private $finished = False;
    private $b4Yield  = True;
    private $incomingValue = array();
    private $name = null;
    private $ptid = null; //parent task id
    
    function __construct($opt=null, callable $script) {
        $this->script = $script;
        $this->name = (isset($opt['name'])) ? $opt['name'] : null;
    }
    
    function __destruct() {
        print "killing task".PHP_EOL;
    }
    
    function setTaskId($id) {
        $this->task_id = $id;
    }
    
    function getTaskId() {
        return $this->task_id;
    }
    
    function getId() {
        return $this->getTaskId();
    }
    
    function setState ($state='active') {
        $this->state = 'active';
    }
    
    function isStateActive() {
        return ($this->state == 'active') ? True : False;
    }
    
    function setProxyValue($value) {
        $this->proxyValue[] = $value;
    }
    
    function proxyValueIsSet() {
        if(!empty($this->proxyValue)) {
            return True;
        }
        return False;
    }
    
    function getProxyValue($clear=false) {
        if($clear == True) {
            $r = $this->proxyValue;
            $this->proxyValue = array();
            return $r;
        } else {
            return $this->proxyValue;
        }
    }
    
    function setParentId($ptid) {
        if(is_null($this->ptid) || is_null($ptid)) {
            $this->ptid = $ptid;
            return True;
        }
        return False;
    }
    
    function getParentId()  {
        return $this->ptid;
    }
    
    private function receiveValue() {
        $v = $this->incomingValue;
        $this->incomingValue = array();
        return $v;
    }
    
    public function setRetval($val) {
        $this->retval[] = $val;
    }
    
    private function getRetval() {
        if(!empty($this->retval)) {
            $r = $this->retval;
            $this->retval = array();
            return $r;
        }
        return null;
    }
    
    function run() {
        if(!empty($this->events)) {
            foreach($this->events as $eId => $event ) {
                $e = $event->run();
                
                if(!$event->isValid()) {
                    unset($this->events[$eId]);
                }
            }
        }
        
        $this->script->__invoke($this);
        return $this->getRetval();
        
        
        /*if ($this->b4Yield) {
            $this->b4Yield = false;
            $this->coroutine->current();
            $this->coroutine->send($this);
            return True;
        } else {
            $retval = $this->coroutine->send($this->receiveValue());
            return $retval;
        }*/
        
    }
    
    function addEvent(event $event, $name=null) {
        if(isset($name)) {
            $event->name = $name;
        }
        $this->events[] = $event;
        return True;
    }
    
    function delEvent($id) {
        unset($this->events[$id]);
    }
    
    function bypassRun($send) {
        return $this->__processReturn($this->corutine->send($send));
    }
    
    function isFinished()   {
        if($this->finished == True) {
            return True;
        }
        return False;
    }
    
    function setFinshed($state) {
        if (is_bool($state)) {
            $this->finished = $state;
            return True;
        }
        return False;
    }
    
    function __processReturn($re) {
        foreach($re as $dex=>$dat) {
            if($dat instanceof task) {
                $toSchedular[] = $dat;
            }
            elseif($dat instanceof systemCall) {
                $toScheduler[] = $dat;
            }
        }
        return $toScheduler;
    }
    
    function __invoke() {
        return $this->run();
    }
    
}

?>
