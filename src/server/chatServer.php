<?php

require_once(dirname(__FILE__).'/../core/coStreamSocketServer.php');

class chatServer extends coStreamSocketServer {
    function __construct() {
        parent::__construct("tcp://0.0.0.0:9191");
    }
    
    function run() {
        $this->task = yield;
        while (true) {
            //var_dump($this->stream);
            $this->task->waitFor = $this->waitforRead();
            yield;
            if($conn = @stream_socket_accept($this->stream, 0)) {
                yield array(new task((new chatHandle($conn))->run(), array('name' => 'chatHandle')));
                $conn = null;
            }
        }
    }
}

class chatHandle extends coStreamSocket {
    private $name = null;
    
    function __construct($conn) {
        $this->stream = $conn;
        $this->mkStreamAsync();
    }
    
    function run() {
        $this->task = yield;
        $payload = "";
        $this->task->waitFor = $this->waitForWrite();
        fwrite($this->stream, "Enter name: ");
        
        $this->task->waitFor = $this->waitForRead();
        while (true) {
            yield;
            $read = fread($this->stream, 1024);
            if (strlen($read) > 0) {
                $this->setName($read);
                break;
            }
        }
        
        while (true) {
            $this->task->waitFor = $this->waitForRead();
            yield;
            //var_dump("aaaa", $this->task->proxyValue);
            if(!empty($this->task->proxyValueIsSet())) {
                foreach($this->task->getProxyValue(true) as $proxy) {
                    $this->task->waitFor = $this->waitForWrite();
                    yield;
                    fwrite($this->stream, $proxy->value);
                }
            }
            
            $new = fread($this->stream, 1024);
            if(!empty($new)) {
                $payload .= $new;
            } 
            elseif(!empty($payload)) {
                $msg = $this->formatMsg($payload);
                print $msg;
                $proxy = new coProxyValue($msg);
                $proxy->setType('byParentId');
                yield array($proxy);
                
                $payload = null;
            }
        }
    }
    
    function setName($name) {
        $this->name = substr($name, 0, -1);
    }
    
    function formatMsg($msg) {
        return $this->name.': "'.substr($msg, 0, -1).'"'.PHP_EOL;
    }
    
    function waitForRead() {
        //print "hello".PHP_EOL;
        if(parent::waitForRead()) {
            return True;
        }
        elseif(!empty($this->task->proxyValueIsSet())) {
            print "have proxy";
            return True;
        }
    }
}

?>

