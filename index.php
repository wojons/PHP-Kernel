<?php










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

$kernel->run(11);
?>
