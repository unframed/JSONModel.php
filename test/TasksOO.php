<?php

require_once('test/JSONModelTest.php');

class Task extends JSONMessage {}

class TasksModel extends JSONModel {
    function message (array $map, $encoded=NULL) {
        return new Task($map, $encoded);
    }
    function insert ($task) {
        return parent::insert($task->map);
    }
    function replace ($task) {
        return parent::replace($task->map);
    }
}

class TasksTable extends TasksModel {
    static function columns () {
        return array(
            'task' => 'INTEGER NOT NULL AUTO_INCREMENT',
            'task_name' => 'VARCHAR(255) NOT NULL',
            'task_scheduled_for' => 'INTEGER UNSIGNED NOT NULL',
            'task_completed_at' => 'INTEGER UNSIGNED',
            'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
            'task_modified_at' => 'INTEGER UNSIGNED',
            'task_deleted_at' => 'INTEGER UNSIGNED',
            'task_json' => 'MEDIUMTEXT'
        );
    }
    static function types () {
        return array(
            'task' => 'intval',
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval'
        );
    }
    static function primary () {
        return array('task');
    }
    static function factory (SQLAbstract $sql) {
        return new TasksTable($sql, array(
            'name' => 'task',
            'columns' => TasksTable::columns(),
            'primary' => TasksTable::primary(),
            'types' => TasksTable::types(),
            'domain' => 'test_'
        ));
    }
}

class TasksView extends TasksModel {
    static function columns (SQLAbstract $sql) {
        return (
            "SELECT *,"
            ." (task_scheduled_for > NOW())"
            ." AS task_due,"
            ." (task_completed_at IS NULL OR task_completed_at < NOW())"
            ." AS task_completed,"
            ." (task_deleted_at IS NOT NULL)"
            ." AS task_deleted"
            ." FROM ".$sql->prefixedIdentifier('test_task')
        );
    }
    static function types () {
        return array(
            'task_due' => 'boolval',
            'task_completed' => 'boolval',
            'task_deleted' => 'boolval'
        );
    }
    static function factory (SQLAbstract $sql) {
        return new TasksView($sql, array(
            'name' => 'task_view',
            'columns' => TasksView::columns($sql),
            'primary' => TasksTable::primary(),
            'types' => array_merge(TasksTable::types(), TasksView::types()),
            'domain' => 'test_'
        ));
    }
}

class Application extends JSONModelTest {
    function __construct(SQLAbstract $sql) {
        parent::__construct(
            $sql,
            array('task' => TasksTable::columns()),
            array('task_view' => TasksView::columns($sql)),
            TasksView::types()
        );
    }
    function tasksTable () {
        return TasksTable::factory($this->sql);
    }
    function tasksView () {
        return TasksView::factory($this->sql);
    }
}

// 0483 666 608