<?php

require_once('../src/core/schedular.php');
require_once('../src/core/coStreamSocketServer.php');

$kernel = new scheduler();
$kernel->addTask(array('name' => 'chatServer'), function(&$that) {
    if(!isset($that->super['server'])) {
        $that->super['server'] = new coStreamSocketServer('tcp://0.0.0.0:9191');
    }
    
    if($conn = @stream_socket_accept($that->super['server']->getStream(), 0)) {
        $handler = new task(array('name' => 'chatHandle'), function(&$that) {
            if(is_resource($that->super['conn'])) {
                $that->super['conn']   = new coStreamSocket($that->super['conn']);
                $that->super['typing'] = "";
                $that->super['name']   = stream_socket_get_name($that->super['conn']->getStream(), True);
            }
            
            if($that->super['conn']->canRead()) {
                $new = fread($that->super['conn']->getStream(), 1024);
                if(!empty(rtrim($new))) {
                    $that->super['typing'] .= $new;
                } 
            }
            elseif(!empty($that->super['typing'])) {
                $msg = $that->super['name'].': "'.rtrim($that->super['typing']).'"'.PHP_EOL;
                $that->setRetval((new coProxyValue($msg))->setType('byParentId'));
                $that->super['typing'] = "";
                print $msg;
            } 
            
            //setup events to write messages to this user
            if(!empty($that->proxyValueIsSet())) {
                foreach($that->getProxyValue(true) as $proxy) {
                    $that->events[] = (new event(function(&$that, $data){
                        fwrite($that->super['conn']->getStream(), $data->value);
                    }
                    , $proxy, $that, $that->super['conn']->canWrite()));
                }
            }
        });
        
        $handler->super['conn'] = $conn;
        $that->setRetval($handler);
    }
    
});

$kernel->run(11);

?>
