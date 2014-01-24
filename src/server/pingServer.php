<?php

require_once(dirname(__FILE__).'/../core/coStreamSocketServer.php');

class pingServer extends coStreamSocketServer {
    function __construct() {
        parent::__construct("tcp://0.0.0.0:9898");
    }
    
    function run() {
        $this->task = yield;
        while (true) {
            //var_dump($this->stream);
            $this->task->waitFor = $this->waitforRead();
            yield;
            if($conn = @stream_socket_accept($this->stream, 0)) {
                yield array(new task((new pingHandle($conn))->run(), array('name' => 'pingHandle')));
                $conn = null;
            }
        }
    }
}

class pingHandle extends coStreamSocket {
    function __construct($conn) {
        $this->stream = $conn;
        $this->mkStreamAsync();
    }
    
    function run() {
        $this->task = yield;
        $payload = "";
        while (true) {
            $this->task->waitFor = $this->waitForRead();
            yield;
            $new = fread($this->stream, 1024);
            if(!empty($new)) {
                $payload .= $new;
            } 
            elseif(!empty($payload)) {
                print $payload;
                $this->task->waitFor = $this->waitForWrite();
                yield;
                fwrite($this->stream, 'ping: "'.substr($payload, 0, -1).'"'.PHP_EOL);
                $payload = null;
            }
        }
    }
}

?>
