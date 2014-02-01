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
        elseif(strlen($buff_size = $this->read_buffer) < $this->read_buffer_max) {
            return $this->read_buffer_max - strlen($buff_size);
        }
        return False;
    }
    
    function readEvent(&$that, $data) {
        yield;
        $stream =& $this->task->super['conn'];
        $fd     =& $stream->getFD();
        $task   =& $this->task;
        $none   = array();
        //$readS  = array($fd, $stream->stream);
        
        while(True) {
            $availReadBuffer = $stream->availReadBuffer();
            
            if(isset($task->pending_fd[$fd]['read']) && $task->pending_fd[$fd]['read'] == True && ($availReadBuffer == True || $availReadBuffer > 0)) {
            //if( ($availReadBuffer == True || $availReadBuffer > 0)) {
                $read = $this->doRead();
                if($read === False) {
                    exit();
                }
                unset($task->pending_fd[$fd]['read']);
            }
            
            yield;
        }
    }
    
    function watchEvent($that, $data) {
        yield;
        $stream  =& $this->task->super['conn'];
        $fd      =& $stream->getFD();
        $task    =& $this->task;
        $none    = array();
        $sockets = array($fd, $stream->stream);
        while(true) {
            $task->setRetval((new systemCall(function($task, $scheduler) use ($sockets, $none){
                $scheduler->addStream2Watch($sockets, $sockets, $none);
            })));
            $task->setRetval(new systemCall(function($task, $scheduler) use ($fd){
                if($pending = $scheduler->isPendingSelect($fd)) {
                    if(isset($pending['read'])) {
                        $task->pending_fd[$fd]['read'] = True;
                    }
                    if(isset($pending['write'])) {
                        $task->pending_fd[$fd]['write'] = True;
                    }
                    if(isset($pending['error'])) {
                        $task->pending_fd[$fd]['error'] = True;
                    }
                    $scheduler->delPendingSelect($fd);
                }
            }));
            
            yield;
        }
    }
    
    function ioEvent($that, $data) {
        yield;
        $stream =& $this->task->super['conn'];
        $fd     =& $stream->getFD();
        $task   =& $this->task;
        $none    = array();
        
        while(True) {
            
            $rSockets = array($this->stream);
            $wSockets = array($this->stream);
            
            if ($select_count = stream_select($rSockets, $wSockets, $none, 0) > 0) {
            
                $availReadBuffer = $this->availReadBuffer();
                
                if(!empty($rSockets) && ($availReadBuffer == True || $availReadBuffer > 0)) {
                //if( ($availReadBuffer == True || $availReadBuffer > 0)) {
                    $read = $this->doRead($availReadBuffer);
                    if($read === False) {
                        exit();
                    }
                }
                
                if(!empty($wSockets) && !empty($this->write_buffer)) {
                    $written = @fwrite($stream->stream, $this->write_buffer);

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
                    
                }
            } else {
                
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
    
    function writeEvent(&$that, $data) {
        yield;
        $stream =& $this->task->super['conn'];
        $fd     =& $stream->getFD();
        $task   =& $this->task;
        $none   = array();
        //$writeS = array($fd, $stream->getStream());
        
        while(True) {
            //print "write loop".PHP_EOL;
            if(isset($task->pending_fd[$fd]['write']) && $task->pending_fd[$fd]['write'] == True && !empty($this->write_buffer)) {
            //if( !empty($this->write_buffer)) {
                
                $written = @fwrite($stream->stream, $this->write_buffer);
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
            } else {
                //var_dump($this->doRead());
            }

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
