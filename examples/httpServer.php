<?php

require_once('../src/core/schedular.php');
require_once('../src/core/coStreamSocketServer.php');
require_once('../src/server/httpServer.php');

$kernel = new scheduler();
$kernel->addTask(array('name' => 'httpServer'), function(&$that) {
    if(!isset($that->super['server'])) {
        $that->super['server'] = new coStreamSocketServer('tcp://0.0.0.0:9191');
        $that->super['count']  = 0;
    }
    //print (memory_get_usage(true)/1048576).PHP_EOL;
    if($conn = @stream_socket_accept($that->super['server']->getStream(), 0)) {
        $handler = new task(array('name' => 'httpRequest'), function(&$that) {
            if(is_resource($that->super['conn'])) {
                $that->super['conn']   = new httpRequest($that->super['conn'], $that);
                $that->super['remote'] = stream_socket_get_name($that->super['conn']->getStream(), True);
                $that->super['local']  = stream_socket_get_name($that->super['conn']->getStream(), False);
                $that->super['conn']->bootstrapRequest();
                
                
            }
            if($that->super['conn']->reqReady() == True && $that->super['conn']->headersSent() == False) {
                //print_r($that->super['conn']->reqGlobal);
                $that->super['conn']->addHeader("", "HTTP/1.1 200 OK");
                $that->super['conn']->addHeader("Date", gmdate('D, d M Y H:i:s ', time()) . 'GMT');
                $that->super['conn']->bodyWrite("fsajflksdajflksadjflk;sajfklsadfjsda");
            }
            
            if($that->super['conn']->bodySent() == True) {
                fclose($that->super['conn']->getStream());
                $that->setFinshed(True);
            }
        });
        
        $handler->super['conn'] = $conn;
        $that->setRetval($handler);
    }
    //print (memory_get_usage(true)/1048576).PHP_EOL;
    unset($handler);
    /*print (memory_get_usage(true)/1048576).PHP_EOL;
    print "========".PHP_EOL;*/
    
    /*(if($that->super['count'] == 1) {
        xdebug_start_trace('/tmp/trace/'.time());
        $that->super['count']++;
    } else {
        if($conn != false) {
            $that->super['count']++;
            //print $that->super['count'].PHP_EOL;
        }
    }
    if($that->super['count'] == 100) {
        exit();
    }*/
});


$kernel->run(null);
?>
