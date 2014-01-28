<?php

//xdebug_start_trace('/tmp/trace/'.time());


require_once(dirname(__FILE__).'/task.php');
require_once(dirname(__FILE__).'/systemCall.php');
require_once(dirname(__FILE__).'/corutineReturnValue.php');
require_once(dirname(__FILE__).'/util.php');

class scheduler {
    
    private $max_tasks_per_map = 500; //the max number of tasks before a new map is created
    private $max_map_count     = 100; // the max number of maps
    
    private $task_ptid   = array(); // tasks maped to there parent task id.
    private $task_map    = array(); // maps with all there tasks
    private $tasks       = array(); // all the tasks
    private $tasks2map   = array(); // task id => map id
    private $map_counts  = array(); // number of tasks in each map (faster then runing count all the time)
    private $total_maps  = 0; // total number of maps in the system
    private $total_tasks = 0; // total number of tasks in the system faster then running a count on $this->tasks
    private $curr_task   = null; //current task in scope
    private $loadAvg     = null;
    private $loadAvgRaw  = null;
    private $settings    = array('loadAvg' => False);
    
    function run($passes=null) {
        $passes = (!is_int($passes)) ? $passes : null;
        $count  = 0;
        $this->loadAvgRaw = (new arrayQueue(900));
        //print_r($this->debug());
        do{
            
            if($this->settings['loadAvg']) { $loadAvgPass = microtime(True); }
            
            //print_r(array('ptid' => $this->task_ptid));
            if(!empty($this->total_tasks) || $this->__rebuildTaskTotal()) {
                foreach($this->task_map as $map_id=>&$map) {
                    //var_dump($map_id, $map);
                    if($this->map_counts[$map_id] > 0) foreach($map as $task_id=>&$task) {
                        //var_dump($task);
                        $this->curr_task =& $task;
                        $retval = $task->run();
                        if(!empty($retval) && !is_bool($retval)) foreach($retval as &$dat)   {
                            if ($dat instanceof systemCall) {
                                $dat($task, $this);
                            }
                            elseif($dat instanceof task) {
                                $this->__setTaskParentId($task->getTaskId(), $this->addTask(array(), $dat));
                            }
                            elseif($dat instanceof coProxyValue) {
                                $this->__processProxy($dat, $this->curr_task);
                            }
                        }
                        unset($retval);
                        
                        if ($task->isFinished()) {
                            $this->tasks[$task_id]->__delSelf();
                            $this->delTask($task_id);
                        }
                        
                        
                    }
                }
            } else {
                print "no tasks".PHP_EOL;
                $count++;
            }
            
            if($this->settings['loadAvg']) { $this->updateLoadAvg(microtime(True)-$loadAvgPass);}
            
            
        } while( (is_null($passes) || $passes< $count) && ($this->total_tasks > 0 || $this->__rebuildTaskTotal()) );
        print "bye";
    }
    
    function debug() {
        return array('total_maps' => $this->total_maps, 'total_tasks' => $this->total_tasks, 'map_counts' => $this->map_counts, 'tasks' => $this->tasks, 'task_map' => $this->task_map, 'task2map' =>$this->tasks2map, 'task_parent' => $this->task_ptid);
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
    
    function addTask($opt, $cb) {
        if ($cb instanceof task) {
            $this->tasks[] =& $cb;
        } else {
            $this->tasks[] = new task($opt, $cb);
        }
        
        $priority = isset($opt['priority']) ? $opt['priority'] : 1;
        
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
            $this->__unsetTaskParentId($task_id);
            //debug_zval_dump($this->tasks[$task_id]);
            foreach($map_ids as $dex=>$dat) {
                $this->total_tasks--;
                $this->map_counts[$dat]--;
                unset($this->task_map[$dat][$task_id]);
            }
            $this->tasks[$task_id] = null;
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
    
    private function __processProxy(&$proxy, &$task) {
        switch($proxy->type) {
            case 'byParentId':
                $this->__proxyByParent($proxy, $task->getParentId(), array($task->getId()));
                break;
        }
    }
    
    private function updateLoadAvg($passTime) {
        $this->loadAvg = Null; //set to blank so we know to recompute
        $last = $this->loadAvgRaw->prepend($passTime);
        //var_dump($this->loadAvgRaw);
    }
    
    public function getLoadAvg() {
        if (is_null($this->loadAvg)) {
            if(!$this->loadAvgRaw->isEmpty()) {
                $this->loadAvg = array(
                    array_sum(array_splice($this->loadAvgRaw->getArray(), 0, 60))/60,
                    array_sum(array_splice($this->loadAvgRaw->getArray(), 0, 300))/300,
                    array_sum(array_splice($this->loadAvgRaw->getArray(), 0, 900))/900,
                );
            }
        }
        return $this->loadAvg;
    }
    
    public function areAnyChildTasks() {
        return !empty($this->task_ptid) ? True : False;
    }
    
    public function getChildTasks($parent_id) {
        return $this->task_ptid[$parent_id];
    }
    
    private function __setTaskParentId($parent_id, $task_id) {
        $this->task_ptid[$parent_id][$task_id] =& $this->tasks[$task_id];
        $this->tasks[$task_id]->setParentId($parent_id);
    }
    
    private function __unsetTaskParentId($task_id) {
        unset($this->task_ptid[$this->tasks[$task_id]->getParentId()][$task_id]);
        if(empty($this->task_ptid[$this->tasks[$task_id]->getParentId()])) { //if its empty remove it
            unset($this->task_ptid[$this->tasks[$task_id]->getParentId()]);
        }
        $this->tasks[$task_id]->setParentId(null);
    }
    
    /* will proxy data via the ptid */
    private function __proxyByParent($proxy, $parent_id, $exclude) {
        foreach($this->task_ptid[$parent_id] as $dex=>&$dat) {
            if(in_array($dex, $exclude)) {
                continue;
            }
            
            $dat->setProxyValue($proxy);
        }
    }
}

?>
