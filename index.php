<?php

class scheduler {
    
    private $max_tasks_per_map = 50; //the max number of tasks before a new map is created
    private $max_map_count     = 10; // the max number of maps
    
    private $task_map    = array(); // maps with all there tasks
    private $tasks       = array(); // all the tasks
    private $tasks2map   = array(); // task id => map id
    private $map_counts  = array(); // number of tasks in each map (faster then runing count all the time)
    private $total_maps  = 0; // total number of maps in the system
    private $total_tasks = 0; // total number of tasks in the system faster then running a count on $this->tasks
    
    function run($passes=null) {
        $passes = (!is_int($passes)) ? $passes : null;
        $count  = 0;
        //print_r($this->debug());
        do{
            if(!empty($this->total_tasks) || $this->__rebuildTaskTotal()) {
                foreach($this->task_map as $map_id=>$map) {
                    //var_dump($map_id, $map);
                    if($this->map_counts[$map_id] > 0) foreach($map as $task_id=>&$task) {
                        //var_dump($task);
                        $retval = $task->run();
                        if(!empty($retval) && !is_bool($retval)) foreach($retval as $dat)   {
                            if ($dat instanceof systemCall) {
                                $dat($task, $this);
                            }
                            elseif($dat instanceof task) {
                                $task_id = $this->addTask($dat);
                            }
                        }
                        
                        if ($task->isFinished()) {
                           $this->delTask($task_id);
                        }
                    }
                }
            } else {
                print "no tasks".PHP_EOL;
            }
            $count++;
        } while( (is_null($passes) || $passes< $count) && ($this->total_tasks > 0 || $this->__rebuildTaskTotal()) );
    }
    
    function debug() {
        return array('total_maps' => $this->total_maps, 'total_tasks' => $this->total_tasks, 'map_counts' => $this->map_counts, 'tasks' => $this->tasks, 'task_map' => $this->task_map, 'task2map' =>$this->tasks2map);
    }
    
    private function __addTaskToMap($task_id, $priority=1) {
        $priority = (is_int($priority) && $priority > 0) ? $priority : 1;
        for($i=1; $i<=$priority; $i++) {
            $map_id = $this->__selectOpenMap($task_id);
            //print "map_id:".$map_id.":".$i.":".$task_id.PHP_EOL;
            if($map_id !== False) {
                $this->task_map[$map_id][$task_id] =& $this->tasks[$task_id];
                $this->tasks2map[$task_id][] = $map_id;
                $this->total_tasks++;
                $this->map_counts[$map_id]++;
            } else {
                return $i*-1; //return the number of instances of task we were able to place
            }
        }
        return True;
    }
    
    function addTask($cb, $priority=1, $opt=null) {
        if ($cb instanceof task) {
            $this->tasks[] =& $cb;
        } else {
            $this->tasks[] = new task($cb, $opt);
        }
        
        end($this->tasks);
        $task_id = key($this->tasks);
        $map_counts = $this->__addTaskToMap($task_id, $priority);
        
        if($map_counts < 0) {//unable to apply to a map
            print "failed to add to map".PHP_EOL;
        }
        
        $this->tasks[$task_id]->setTaskId($task_id);
        return $task_id;
    }
    
    function delTask($task_id) {
        if(isset($this->tasks[$task_id])) {
            $map_ids = $this->tasks2map[$task_id];
            foreach($map_ids as $dex=>$dat) {
                $this->total_tasks--;
                $this->map_counts[$dat]--;
                unset($this->task_map[$dat][$task_id]);
            }
            unset($this->tasks2map[$task_id], $this->tasks[$task_id]);
            //print "delting task:$task_id".PHP_EOL;
            return True;
        }
        return False;
    }
    
    private function __addMap() {
        if($this->__openMapSlot()) {
            $this->task_map[] = array();
            end($this->task_map);
            $key = key($this->task_map);
            
            $this->map_counts[$key] = array();
            $this->total_maps += 1;
            return $key;
        }
        return False;
    }
    
    private function __selectOpenMap($task_id, $rebuild=True) {
        $this->__sortMapCounts();
        if($rebuild) {
            $this->__rebuildMapCountsAll();
        }
        
        if(!empty($this->map_counts)){
        
            foreach($this->map_counts as $dex=>$dat) {
                if(isset($this->task_map[$dex][$task_id])) {
                    continue;
                }
                elseif(!$this->__isMapFull($dex)) {
                    return $dex;
                }
                elseif($rebuild == False) { //we should have had open space there but did not
                    $rebuild = True;
                    $this->__rebuildMapCountsAll();
                    continue;
                }
                elseif($this->__openMapSlot()) {
                    return $this->__addMap();
                }
            }
            
            if($this->__openMapSlot()) {
                return $this->__addMap();
            }
            
        } else { //we dont have any maps
            return $this->__addMap();
        }
        return False;
    }
    
    private function __sortMapCounts() {
        return arsort($this->map_counts);
    }
    
    private function __rebuildMapCounts($map) {
        if(isset($this->task_map[$map])) {
            $this->map_counts[$map] = count($this->task_map[$map]);
            return $this->map_counts[$map];
        }
        return False;
    }
    
    private function __rebuildMapCountsAll() {
        foreach($this->task_map as $dex=>$dat) {
            $this->__rebuildMapCounts($dex);
        }
    }
    
    private function __rebuildMapTotal() {
        $this->total_maps = count($this->map_counts);
        return $this->total_maps;
    }
    
    private function __rebuildTaskTotal() {
        $this->__rebuildMapCountsAll();
        $this->total_tasks = array_sum($this->map_counts);
        return $this->total_tasks;
    }
    
    private function __isMapFull($map) {
        if(!isset($this->map_counts[$map]) || $this->map_counts[$map] < $this->max_tasks_per_map || $this->max_tasks_per_map == -1) {
            return False;
        }
        return True;
    }
    
    private function __openTaskSlots($map) {
        if(!$this->__isMapFull($map)) {
            if($this->max_tasks_per_map == -1) {
                return True; //inifintatny
            } else {
                return $this->max_tasks_per_map-$this->map_counts[$map];
            }
        }
    }
    
    private function __openMapSlot() {
        if($this->max_map_count == -1 || $this->total_maps < $this->max_map_count || $this->__rebuildMapTotal() < $this->max_map_count) {
            return True;
        }
        return false;
    }
}

class task {
    private $state;
    public $waitFor  = null;
    private $post_run = array();
    private $task_id  = null;
    private $finished = False;
    private $b4Yield  = True;
    private $incomingValue = null;
    private $name = null;
    
    function __construct(Generator $coroutine, $opt=null) {
        $this->coroutine = $coroutine;
        $this->name = (isset($opt['name'])) ? $opt['name'] : null;
    }
    
    function setTaskId($id) {
        $this->task_id = $id;
    }
    
    function setState ($state='active') {
        $this->state = 'active';
    }
    
    function isStateActive() {
        return ($this->state == 'active') ? True : False;
    }
    
    function incoingValue($value) {
        $this->incomingValue();
    }
    
    private function receiveValue() {
        $v = $this->incomingValue;
        $this->incomingValue = null;
        return $v;
    }
    
    function run() {
        if(isset($this->waitFor) && is_callable($this->waitFor) && $this->waitFor() == False) {
            print "NULL".PHP_EOL;
            return Null;
        }
        
        if ($this->b4Yield) {
            $this->b4Yield = false;
            $this->coroutine->current();
            $this->coroutine->send($this);
            return True;
        } else {
            $retval = $this->coroutine->send($this->receiveValue());
            return $retval;
        }
        
        /* some sort of do after the corunitne runs thingy
         * if(!empty($this->)) {
            foreach($this->post_run as $dex=>$dat) {
                $dat();
            }
        }
        */
    }
    
    function bypassRun($send) {
        return $this->__processReturn($this->corutine->send($send));
    }
    
    function isFinished()   {
        if($this->finished == True || !$this->coroutine->valid() == True) {
            return True;
        }
        return False;
    }
    
    function __processReturn($re) {
        foreach($re as $dex=>$dat) {
            if($dat instanceof task) {
                $toSchedular[] = $dat;
            }
            elseif($dat instanceof systemCall) {
                $toScheduler[] = $dat;
            }
        }
        return $toScheduler;
    }
    
    function __invoke() {
        return $this->run();
    }
    
}

class systemCall {
    protected $callback;
    
    public function __construct(callable $callback) {
        $this->callback = $callback;
    }
    
    public function __invoke(task $task, scheduler $schduler) {
        $callback =& $this->callback;
        return $callback($task, $schduler);
    }
}

class corutineReturnValue {
    protected $value;
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function getValue() {
        return $this->value;
    }
}

class coStream {
    protected $stream;
    
    function mkStreamAsync($timeout=0) {
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
    }
    
    function waitForRead() {
        $stream = array($this->stream);
        $write  = Null;
        $except = Null;
        //print "waiting";
        if(stream_select($stream, $write, $except, 0, 0) > 0) {
            return True;
        }
        return False;
    }
    
    function waitForWrite() {
        $stream = array($this->stream);
        $read   = Null;
        $except = Null;
        if(stream_select($read, $stream, $except, 0) > 0) {
            return True;
        }
        return False;
    }
}

class coStreamSocketServer extends coStream {
    
    function __construct($socket, $timeout=0) {
        $error = array('errno' => null, 'errstr' => null);
        $this->stream = stream_socket_server("tcp://0.0.0.0:9898", $error['errno'], $error['errstr']);
        //var_dump($this->stream)."co".PHP_EOL;
        stream_set_blocking($this->stream, 0);
        stream_set_timeout($this->stream, $timeout);
    }
    
    /*function accept() {
        while (true) {
            $this->task->waitFor = $this->waitforRead();
            yield;
            return stream_socket_accept($this->stream, 0);
        }
    }*/
}

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

class pingHandle extends coStream {
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

function retval ($value) {
    return new corutineReturnValue($value);
}

$kernel = new scheduler();
function task1() {
    for ($i = 1; $i <= 10; ++$i) {
        echo "This is task 1 iteration $i.\n";
        yield;
    }
}
function task2() {
    for ($i = 1; $i <= 10; ++$i) {
        echo "This is task 2 iteration $i.\n";
        yield;
    }
}

$me = function () {
    for ($i = 1; $i <= 10; ++$i) {
        echo "This is task 3 iteration $i.\n";
        yield;
    }
};

$me2 = function() {
    $task = yield;
    print "ff".PHP_EOL;
    var_dump($task);
    $a = null;
    (new pingServer($task));
    print "fff";
};

/*$kernel->addTask(task1(), 1);
$kernel->addTask(task2(), 5);
$kernel->addTask($me(), 10);*/
$test = (new pingServer())->run();
$kernel->addTask($test, 1, array('name' => 'pingServer'));

$kernel->run(11);
?>
