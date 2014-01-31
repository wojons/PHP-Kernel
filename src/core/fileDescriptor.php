<?php

/*
 * 
 * Implentaon of a file descriptor table that will exist in each task
 * optimzed for getting the resource and not figuring out what type of
 * resoruce it is
 * 
 */

class fileDescriptor {
    
    private $table = array();
    private $type  = array();
    
    static $next_fd = 0; //this will be global for all kernels running should be fixed later
    
    function __construct() {
        // will build this function out as needed
        //$this->add(Null); //stdin
        //$this->add(Null); //stdout
        //$this->add(Null); //stderr
    }
    
    function __destruct() {
        $micro = microtime(true);
        foreach($this->table as $dex=>$dat) {
            unset($this->table[$dex]);
        }
        
        foreach($this->type as $dex=>$dat) {
            foreach($dat as $dex2=>$dat2) {
                unset($this->type[$dex][$dex2]);
            }
        }
    }
    
    function getNextFd() {
        return static::$next_fd++;
    }
    
    function add(&$fd, $type=null) {
        $type = ($type === Null) ? 'main' : $type;
        $fd_id                     = $this->getNextFd();
        $this->table[$fd_id]       =& $fd;
        $this->type[$type][$fd_id] =& $this->table[$fd_id];
        return $fd_id;
    }
    
    function del($fd) {
        if(isset($this->table[$fd])) {
            foreach($this->type as $dex=>$dat) {
                unset($this->table[$fd], $this->type[$dex][$fd]);
            }
            unset($this->table[$fd]); //only used if its rouge;
        }
    }
    
    function &get($fd) {
        return $this->table[$fd];
    }
}

?>
