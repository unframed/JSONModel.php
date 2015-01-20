<?php

require_once('test/TasksOO.php');

function test_create ($sql) {
    $app = new Application($sql);
    $app->tasks()->create();
    $app->tasksView()->create();
}

$t = new TestMore();

$t->plan(2);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');

$t->is(TRUE, TRUE, 'openMySQL did not fail');

test_create(new SQLAbstractPDO($pdo, 'wp_'));

$t->is(TRUE, TRUE, 'test_create did not fail');
