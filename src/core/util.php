<?php

function retval ($value) {
    return new corutineReturnValue($value);
}

class arrayQueue {
    
    protected $limit = Null;
    protected $count = 0;
    protected $arr = array();
    
    function __construct($limit=null, $arr=null) {
        $this->limit = $limit;
        $this->arr = is_array($arr) ? $arr : array();
    }
    
    function getCount() {
        return $this->count;
    }
    
    function isFull() {
        if(!is_null($this->limit) && $this->limit == $this->count) {
            return True;
        }
        return False;
    }
    function isEmpty() {
        if($this->count == 0) {
            return True;
        }
        return False;
    }
    
    function append($new) {
        if($this->isFull()) {
            $end = array_shift($this->arr);
        } else {
            $this->count++;
            $end = Null;
        }
        $this->arr[] = $new;
        return $end;
    }
    
    function prepend($new) {
        if($this->isFull()) {
            $first = array_pop($this->arr);
        } else {
            $this->count++;
            $first = Null;
        }
        array_unshift($this->arr, $new);
        return $first;
    }
    
    function getArray() {
        return $this->arr;
    }
}

?>
