<?php

class event {
    
    public $event;
    public $check = null;
    public $data  = null;
    
    public $vaild = True;
    public $name  = "";
    
    function __construct(&$task, $data, $event=null, $check=null) {
        $this->task  =& $task;
        
        if((is_callable($data) || $data instanceof Generator)&& is_null($event) && is_null($check)) {
            $this->event = $data;
        }
        elseif((is_callable($data) || $data instanceof Generator) && is_callable($event) && is_null($check)) {
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
        if(!is_null($this->check) && $this->check->__invoke() == False) {
            return False;
        }
        
        if(is_callable($this->event)) {
            $retval = $this->event->__invoke($this->task, $this->data);
            $this->vaild = False;
            return $retval;
            
        } elseif($this->event instanceof Generator) {
            if(empty($this->pending)) {
                return $this->event->next();
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
