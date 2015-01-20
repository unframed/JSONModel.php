<?php

require_once('test/TasksOO.php');

function test_insert ($sql, $message) {
    $app = new Application($sql);
    $tasks = $app->tasks();
    $task = $tasks->insert($message);
    $encoded = json_encode($task->map);
    $stored = $tasks->fetchById($task->getInt('task'))->encoded();
    return ($encoded == $stored);
}

$t = new TestMore();

$t->plan(2);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');

$t->is(TRUE, TRUE, 'openMySQL did not fail');

$sql = new SQLAbstractPDO($pdo, 'wp_');

$t->is(TRUE, test_insert($sql, new JSONMessage(array(
    'task_name' => 'in one hour',
    'task_created_at' => time(),
    'task_scheduled_for' => time()+3600,
    'extensions' => array(
        'list' => array(1,2,3)
        )
    ))), 'test_insert did not fail');
