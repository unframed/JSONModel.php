<?php

require_once('test/JSONModelTest.php');

class Task extends JSONMessage {}

class Tasks extends JSONModel {
    static function columns () {
        return array(
            'task' => 'INTEGER AUTOINCREMENT PRIMARY KEY',
            'task_name' => 'VARCHAR(255) NOT NULL',
            'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
            'task_modified_at' => 'INTEGER UNSIGNED',
            'task_deleted_at' => 'INTEGER UNSIGNED',
            'task_json' => 'MEDIUMTEXT'
            );
    }
    static function types() {
        return array(
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval'
            );
    }
    function message($map, $encoded=NULL) {
        return new Task($map, $encoded);
    }
    function __construct ($sql, $types) {
        parent::__construct($sql, $types, array(
            'name' => 'task',
            'columns' => self::columns(),
            'domain' => 'test_'
            ));
    }
}

class TasksView extends JSONModel {
    static function columns ($sql) {
        return array(
            "SELECT *,"
            ." (task_scheduled_for > NOW())"
            ." AS task_due,"
            ." (task_completed_at IS NULL OR task_completed_at < NOW())"
            ." AS task_completed,"
            ." (task_deleted_at NOT NULL)"
            ." AS task_deleted"
            ." FROM ".$sql->prefixedIdentifier('task')
            );
    }
    static function types() {
        return array(
            'task_due' => 'boolval',
            'task_completed' => 'boolval',
            'task_deleted' => 'boolval'
            );
    }
    function message($map, $encoded=NULL) {
        return new Task($map, $encoded);
    }
    function __construct ($sql, $types) {
        parent::__construct($sql, $types, array(
            'name' => 'task_view',
            'columns' => self::columns($sql),
            'idColumn' => 'task',
            'jsonColumn' => 'task_json',
            'domain' => 'test_'
            ));
    }
}

class Application extends JSONModelTest {
    function __construct($sql) {
        parent::__construct(
            $sql,
            array(
                'task' => Tasks::columns()
            ), array(
                'task_view' => TasksView::columns($sql)
            ), array_merge(
                Tasks::types(),
                TasksView::types()
            )
        );
    }
    function tasks () {
        return new Tasks($this->sql, $this->types);
    }
    function tasksView () {
        return new TasksView($this->sql, $this->types);
    }
}
