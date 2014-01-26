<?php

require_once(dirname(__FILE__).'/coStreamSocket.php');

class coStreamSocketServer extends coStreamSocket {
    
    function __construct($socket, $timeout=0) {
        $error = array('errno' => null, 'errstr' => null);
        $this->stream = stream_socket_server($socket, $error['errno'], $error['errstr']);
        //var_dump($this->stream)."co".PHP_EOL;
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
    }
}

?>
