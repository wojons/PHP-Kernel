<?php

class coStreamSocket {
    protected $stream;
    
    function __construct($stream=null) {
        if(is_resource($stream)) {
            $this->stream = $stream;
        }
    }
    
    function mkStreamAsync($timeout=0) {
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
    }
    
    function &getStream() {
        return $this->stream;
    }
    
    function canRead() {
        $stream = array($this->stream);
        $write  = Null;
        $except = Null;
        //print "waiting";
        if(stream_select($stream, $write, $except, 0, 0) > 0) {
            return True;
        }
        return False;
    }
    
    function canWrite() {
        $stream = array($this->stream);
        $read   = Null;
        $except = Null;
        if(stream_select($read, $stream, $except, 0) > 0) {
            return True;
        }
        return False;
    }
    
    function isExcept() {
        $stream = Null;
        $write  = Null;
        $except = array($this->stream);
        //print "waiting";
        if(stream_select($stream, $write, $except, 0, 0) > 0) {
            return True;
        }
        return False;
    }
}

?>
