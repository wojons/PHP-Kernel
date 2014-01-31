<?php

class event {
    
    public $event;
    public $check = null;
    public $data  = null;
    
    public $checkIs;
    public $eventIs;
    
    public $vaild = True;
    public $name  = "";
    
    function __construct(&$task, $data, $event=null, $check=null) {
        $this->task  =& $task;
        
        if((is_callable($data) || $data instanceof Generator)&& $event === Null && $check === NUll) {
            $this->event = $data;
        }
        elseif((is_callable($data) || $data instanceof Generator) && is_callable($event) && $check === Null) {
            $this->event = $data;
            $this->check = $event;
        }
        elseif((!is_callable($data) || !$data instanceof Generator) && (is_callable($event) || $event instanceof Generator)) {
            $this->data  = $data;
            $this->event = $event;
        }
        elseif(!is_callable($data) && is_callable($event) && is_callable($check)) {
            $this->data  = $data;
            $this->event = $event;
            $this->check = $check;
        }
        
        if(is_callable($this->event)) {
            $this->eventIs = 'callback';
        } else {
            $this->eventIs = 'generator';
        }
        
        /*if($this->check !== Null) {
            if(is_callable($this->event)) {
                $this->checkIs == 'callback';
            } else {
                $this->checkIs = 'generator';
            }
        }*/
        
        //print "a9"; var_dump(array($task, $data, $event, $check));
    }
    
    function getPending($del=false) {
        $pending = $this->pending;
        $this->pending = array();
        return $pending;
    }
    
    function isValid() {
        if (!$this->vaild || ($this->event instanceof Generator && !$this->event->valid())) {
            "not vaild";
            return False;
        }
        return True;
    }
    
    function run() {
        if(!empty($this->check) && $this->check->__invoke() == False) {
            return False;
        }
        
        if($this->eventIs == 'callback') {
            $retval = $this->event->__invoke($this->task, $this->data);
            $this->vaild = False;
            return $retval;
            
        } elseif($this->eventIs == 'generator') {
            if(empty($this->pending)) {
                //print $this->name.PHP_EOL;
                return $this->event->send(null);
            }
            return $this->event->send($this->getPending(True));
        }
        
        print "req run fail: ".$this->name.PHP_EOL;
        var_dump($this->event);
        return Null;
    
    }
    
    function __delSelf() {
        unset($this->task, $this->event, $this->check, $this->data);
    }
}

?>
