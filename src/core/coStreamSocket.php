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
        elseif(strlen($this->read_buffer) < $this->read_buffer_max) {
            return $this->read_buffer_max - strlen($this->read_buffer);
        }
        return False;
    }
    
    function readEvent(&$that, $data) {
        yield;
        $stream =& $this->task->super['conn'];
        $fd     =& $stream->getFD();
        $task   =& $this->task;
        $none   = array();
        $readS  = array($fd, $stream->getStream());
        
        while(True) {
            $availReadBuffer = $stream->availReadBuffer();
            
            if(isset($task->pending_fd[$fd]['read']) && $task->pending_fd[$fd]['read'] == True && ($availReadBuffer == True || $availReadBuffer > 0)) {
                $read = $this->doRead();
                if($read === False) {
                    exit();
                }
                unset($task->pending_fd[$fd]['read']);
            }
            
            $task->setRetval((new systemCall(function($task, $scheduler) use ($readS, $none){
                $scheduler->addStream2Watch($readS, $none, $none);
            })));
            $task->setRetval(new systemCall(function($task, $scheduler) use ($fd){
                if(isset($scheduler->isPendingSelect($fd)['read']) && $scheduler->isPendingSelect($fd)['read'] == True) {
                    $task->pending_fd[$fd]['read'] = True;
                    $scheduler->delPendingSelect($fd, 'read');
                }
            }));
            
            yield;
        }
    }
    
    private function doRead() {
        $availReadBuffer = $this->availReadBuffer();
        $read = fread($this->getStream(), ((is_int($availReadBuffer)) ? $availReadBuffer : $this->default_read_buffer));
        if($read !== False) {
            $this->read_buffer .= $read;
            return $read;
        }
        return False;
    }
    
    function writeEvent(&$that, $data) {
        yield;
        $stream =& $this->task->super['conn'];
        $fd     =& $stream->getFD();
        $task   =& $this->task;
        $none   = array();
        $writeS = array($fd, $stream->getStream());
        
        while(True) {
            //print "write loop".PHP_EOL;
            if(isset($task->pending_fd[$fd]['write']) && $task->pending_fd[$fd]['write'] == True && !empty($this->write_buffer)) {
                
                $written = @fwrite($stream->getStream(), $this->write_buffer);
                //var_dump($written);
                if($written == False) {
                    $this->isClientAlive = False;
                    $task->setFinshed(True);
                    print "failed write".PHP_EOL;
                    break;
                }
                elseif($written < strlen($this->write_buffer)) {
                    $this->write_buffer = substr($this->write_buffer, $written);
                } else {
                    $this->write_buffer = "";
                }
                
                unset($task->pending_fd[$fd]['write']);
                /*print "said i could write".PHP_EOL;
                var_dump($written);
                var_dump($this->write_buffer);*/
            } else {
                //var_dump($this->doRead());
            }
            
            $task->setRetval((new systemCall(function($task, $scheduler) use ($writeS, $none){
                $scheduler->addStream2Watch($none, $writeS, $none);
            })));
            $task->setRetval(new systemCall(function($task, $scheduler) use ($fd){
                if($scheduler->isPendingSelect($fd)['write'] == True) {
                    $task->pending_fd[$fd]['write'] = True;
                    $scheduler->delPendingSelect($fd, 'write');
                }
            }));
            yield;
        }
    }
    
    function bufferWrite($write) {
        $this->write_buffer .= $write;
        return $this->isClientAlive();
    }
    
    function getWriteBufferSize() {
        return strlen($this->write_buffer);
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
