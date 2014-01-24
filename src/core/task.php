<?php

class task {
    private $state;
    public $waitFor  = null;
    private $post_run = array();
    private $task_id  = null;
    private $finished = False;
    private $b4Yield  = True;
    private $incomingValue = array();
    public $proxyValue = array();
    private $name = null;
    private $ptid = null; //parent task id
    
    function __construct(Generator $coroutine, $opt=null) {
        $this->coroutine = $coroutine;
        $this->name = (isset($opt['name'])) ? $opt['name'] : null;
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
    
    function run() {
        //var_dump($this->waitFor, is_callable($this->waitFor), $this->waitFor);
        if(isset($this->waitFor) && is_callable($this->waitFor) && $this->waitFor() == False) {
            print "NULL".PHP_EOL;
            return Null;
        }
        
        if ($this->b4Yield) {
            $this->b4Yield = false;
            $this->coroutine->current();
            $this->coroutine->send($this);
            return True;
        } else {
            $retval = $this->coroutine->send($this->receiveValue());
            return $retval;
        }
        
        /* some sort of do after the corunitne runs thingy
         * if(!empty($this->)) {
            foreach($this->post_run as $dex=>$dat) {
                $dat();
            }
        }
        */
    }
    
    function bypassRun($send) {
        return $this->__processReturn($this->corutine->send($send));
    }
    
    function isFinished()   {
        if($this->finished == True || !$this->coroutine->valid() == True) {
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
    
    function addEvent($test, $cb) {
        $this->events[] = array('check' => $check, 'callback' => $callback);
    }
    
    function rmEvent($id) {
        unset($this->events[$id]);
    }
    
    function processEvents() {
        foreach($this->events as $dex=>&$dat) {
            if($dat['check']() == True) {
                $dat['callback']();
            }
        }
    }
    
}

?>
