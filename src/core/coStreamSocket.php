<?php

class coStreamSocket {
    protected $stream;
    protected $is_readable         = False;
    protected $is_writable         = False;
    protected $read_buffer         = "";
    protected $write_buffer        = "";
    protected $default_read_buffer = 1024;
    protected $is_error;
    protected $fd;
    
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
    
    function setReadBuffer($bytes) {
        $this->read_buffer_max = $bytes;
    }
    
    function availReadBuffer() {
        if($this->read_buffer_max == -1) {
            return True;
        }
        elseif($this->strlen($this->read_buff) < $this->read_buffer_max) {
            return $this->read_buff_max - strlen($this->read_buffer);
        }
        return False;
    }
    
    function readEvent() {
        yield;
        while(True) {
            $availReadBuffer = $this->availReadBuffer();
            if($this->is_readable == True && ($availReadBuffer == True || $availReadBuffer > 0) {
                $this->read_buffer .= fread($this->stream, ((is_int($availReadBuffer)) ? $availReadBuffer : $this->default_read_buffer));
                $this->is_readable  = False;
            }
            yield (new systemCall(function($task, $scheduler) {
                $scheduler->addStream2Watch(array($this->fd, $this->stream));
            }));
        }
        return False;
    }
    
    function writeEvent() {
        yield;
        while(True)
            if($this->is_writeable == True && !empty($this->write_buffer)) {
                
                $written            = fwrite($this->stream, $this->write_buffer);
                if($written === False) {
                    $this->client_closed = True;
                    break;
                }
                elseif($written < strlen($this->write_buffer)) {
                    $this->write_buffer = substr($this->write_buffer, $written);
                } else {
                    $this->write_buffer = "";
                }
                
                
                $this->is_writable  = False;
            }
            yield;
        }
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
