<?php

require_once('../src/core/schedular.php');
require_once('../src/core/coStreamSocketServer.php');
require_once('../src/server/httpServer.php');

$kernel = new scheduler();
$kernel->addTask(array('name' => 'httpServer'), function(&$that) {
    if(!isset($that->super['server'])) {
        $that->super['server'] = new coStreamSocketServer('tcp://0.0.0.0:9191');
        $that->super['count']  = 0;
        $that->super['anySubTasksExists'] = Null;
        $any_sub_tasks = function(&$that, $data) {
            yield; while(True) {
                yield ((new systemCall(function($task, $scheduler) {
                    $task->super['anySubTasksExists'] = $scheduler->areAnyChildTasks();
                })));
            }
        };
        $that->addEvent((new event($that, $any_sub_tasks($that, null))), "any_sub_tasks");
    }
    //print (memory_get_usage(true)/1048576).PHP_EOL;
    //var_dump($that->super['anySubTasksExists']);

    if($conn = @stream_socket_accept($that->super['server']->getStream(), (int)(!$that->super['anySubTasksExists']))) {
        $handler = new task(array('name' => 'httpRequest'), function(&$that) {
            if(is_resource($that->super['conn'])) {
                $that->super['conn']   = new httpRequest($that->super['conn'], $that);
                $that->super['remote'] = stream_socket_get_name($that->super['conn']->getStream(), True);
                $that->super['local']  = stream_socket_get_name($that->super['conn']->getStream(), False);
                $that->super['conn']->bootstrapRequest();
                
            }
            if($that->super['conn']->reqReady() == True && $that->super['conn']->headersSent() == False) {
                $that->super['conn']->addHeader("HTTP/1.1 200 OK");
                $that->super['conn']->addHeader("Date", gmdate('D, d M Y H:i:s ', time()) . 'GMT');
                $that->super['conn']->bodyWrite("hello world");
            }
            
            //var_dump($that->super['conn']->bodySent());
            //var_dump($that->super['conn']->pendingBody);
            //var_dump($that->super['conn']->getWriteBufferSize());
            if($that->super['conn']->bodySent() == True && empty($that->super['conn']->pendingBody) && $that->super['conn']->getWriteBufferSize() == 0) {

                fclose($that->super['conn']->getStream());
                $that->setFinshed(True);
            }
        });
        
        $handler->super['conn'] = $conn;
        $that->setRetval($handler);
    }
    //print (memory_get_usage(true)/1048576).PHP_EOL;
    unset($handler);
});


$kernel->run(null);
?>
