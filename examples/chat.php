<?php

require_once('../src/core/schedular.php');
require_once('..//src/server/chatServer.php');

$kernel = new scheduler();
$test = (new chatServer())->run();
$kernel->addTask($test, 1, array('name' => 'chatServer'));
$kernel->run(11);

?>
