<?php

require_once('test/TasksOO.php');

function test_update ($test, $sql, $map) {
    $app = new Application($sql);
    $tasks = $app->tasksTable();
    $tasks->update($map);
    $stored = $tasks->fetchById($map['task']);
    $intersect = new JSONMessage(
        @array_intersect_assoc($stored->map, $map)
    );
    $message = new JSONMessage($map);
    $test->is($intersect->uniform(), $message->uniform(), json_encode($map));
}

$t = new TestMore();

$t->plan(2);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');

$t->is(TRUE, TRUE, 'openMySQL did not fail');

$sql = new SQLAbstractPDO($pdo, 'wp_');

test_update($t, $sql, array(
    'task' => 1,
    'task_name' => 'updated in one hour',
    'task_scheduled_for' => time()+3600
));
