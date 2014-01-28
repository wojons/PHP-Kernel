<?php

class systemCall {
    protected $callback;
    
    public function __construct(callable $callback) {
        $this->callback = $callback;
    }
    
    public function __invoke(task $task, scheduler $schduler) {
        $callback = $this->callback;
        return $callback($task, $schduler);
    }
}

?>
