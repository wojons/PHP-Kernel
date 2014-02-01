<?php

class coStreamSocket {
    protected $stream;
    protected $is_readable         = False;
    protected $is_writable         = False;
    protected $read_buffer         = "";
    protected $write_buffer        = "";
    protected $default_read_buffer = 1024;
    protected $read_buffer_max     = 2048;
    protected $is_error;
    protected $fd;
    protected $isClientAlive       = True;
    
    function __construct($stream=null) {
        if(is_resource($stream)) {
            $this->stream = $stream;
        }
    }
    
    function __destruct() {
        
    }
    
    function mkStreamAsync($timeout=0) {
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
        stream_set_read_buffer($this->stream, 0);
        stream_set_write_buffer($this->stream, 0);
    }
    
    function &getStream() {
        return $this->stream;
    }
    
    function setReadBuffer($bytes) {
        $this->read_buffer_max = $bytes;
    }
    
    function availReadBuffer() {
        if($this->read_buffer_max == -1) {
            return True;
        }
        elseif($buff_size = (strlen($this->read_buffer) < $this->read_buffer_max)) {
            return $this->read_buffer_max - $buff_size;
        }
        return False;
    }
    
    function ioEvent($that, $data) {
        yield;
        //$fd     =& $stream->getFD();
        $task   =& $this->task;
        $none    = array();
        
        while(True) {
            
            $rSockets = array($this->stream);
            $wSockets = array($this->stream);
            
            if ($select_count = stream_select($rSockets, $wSockets, $none, 0) > 0) {
            
                $availReadBuffer = $this->availReadBuffer();
                
                if(!empty($rSockets) && ($availReadBuffer == True || $availReadBuffer > 0)) {
                    $read = $this->doRead($availReadBuffer);
                    if($read === False) {
                        exit();
                    }
                }
                
                if(!empty($wSockets) && !empty($this->write_buffer)) {
                    
                    if(($written = @fwrite($this->stream, $this->write_buffer)) > 0) {
                        $this->write_buffer = substr($this->write_buffer, $written);
                    } else {
                        $this->isClientAlive = False;
                        $task->setFinshed(True);
                        print "failed write".PHP_EOL;
                        break;
                    }
                }
            }
            yield;
        }
    }
    
    private function doRead($availReadBuffer=null) {
        $availReadBuffer = ($availReadBuffer==NULL) ? $this->availReadBuffer() : $availReadBuffer;
        $read = fread($this->stream, ((is_int($availReadBuffer)) ? $availReadBuffer : $this->default_read_buffer));
        
        if($read !== False) {
            $this->read_buffer .= $read;
            return $read;
        }
        return False;
    }
    
    function bufferWrite($write) {
        $this->write_buffer .= $write;
        return $this->isClientAlive();
    }
    
    function getWriteBufferSize() {
        return strlen($this->write_buffer);
    }
    
    function isWriteBufferEmpty() {
        return empty($this->write_buffer);
    }
    
    function isClientAlive() {
        if($this->isClientAlive) {
            return True;
        }
        return False;
    }
    
    function setFD($fd) {
        $this->fd = $fd;
        return True;
    }
    
    function getFD() {
        return $this->fd;
    }
}

?>
