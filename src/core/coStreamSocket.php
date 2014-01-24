<?php

class coStreamSocket {
    protected $stream;
    
    function mkStreamAsync($timeout=0) {
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
    }
    
    function waitForRead() {
        $stream = array($this->stream);
        $write  = Null;
        $except = Null;
        //print "waiting";
        if(stream_select($stream, $write, $except, 0, 0) > 0) {
            return True;
        }
        return False;
    }
    
    function waitForWrite() {
        $stream = array($this->stream);
        $read   = Null;
        $except = Null;
        if(stream_select($read, $stream, $except, 0) > 0) {
            return True;
        }
        return False;
    }
}

?>
