<?php

require_once('test/Tasks.php');

function test_create ($sql) {
    $t =
    $app = new Application($sql);
    $app->tasks()->create();
}

$t = new TestMore();

$t->plan(1);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');

$t->is(TRUE, TRUE, 'openMySQL did not fail');

test_create(new SQLAbstractPDO($pdo, 'wp_'));
