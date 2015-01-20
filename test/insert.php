<?php

require_once('test/TasksOO.php');

function test_insert ($sql) {
    $app = new Application($sql);
    $tasks = $app->tasks();
    $tasks->insert(new JSONMessage(array(
        'task_name' => 'in one hour',
        'task_created_at' => time(),
        'task_scheduled_for' => time()+3600,
        'extensions' => array(
            'list' => array(1,2,3)
            )
        )));
}

$t = new TestMore();

$t->plan(2);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');

$t->is(TRUE, TRUE, 'openMySQL did not fail');

test_insert(new SQLAbstractPDO($pdo, 'wp_'));

$t->is(TRUE, TRUE, 'test_insert did not fail');
