<?php

require_once('../src/core/schedular.php');
require_once('..//src/server/pingServer.php');

$kernel = new scheduler();
$test = (new pingServer())->run();
$kernel->addTask($test, 1, array('name' => 'pingServer'));
$kernel->run(11);

?>
