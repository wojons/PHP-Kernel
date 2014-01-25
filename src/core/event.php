<?php

class event {
    
    public $event;
    public $check = null;
    public $vaild = True;
    public $name  = "";
    
    function __construct($event, $data=null, &$task, $check=null) {
        $this->event = $event;
        $this->task  = $task;
        $this->data  = $data;
        
        if ($check == null) {
            $this->check = $check;
        }
    }
    
    function isVaild() {
        if (!$this->vaild || !$this->event->vaild()) {
            "not vaild";
            return False;
        }
        return True;
    }
    
    function run() {
        if(!is_null($this->check) && $this->check == False) {
            return False;
        }
        if(is_callable($this->event)) {
            $retval = $this->event->__invoke($this->task, $this->data);
            $this->vaild = False;
            return $retval;
        }
        return $this->event-send();
    }
}

?>
