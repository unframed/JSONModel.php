<?php

require_once('test/JSONModelTest.php');

class Task extends JSONMessage {}

class TasksModel extends JSONModel {
    function message($map, $encoded=NULL) {
        return new Task($map, $encoded);
    }
}

class TasksTable extends TasksModel {}

class TasksView extends TasksModel {}

class Application extends JSONModelTest {
	function __construct($sql) {
		parent::__construct($sql, array(
            'task' => array(
                'task' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'task_name' => 'VARCHAR(255) NOT NULL',
                'task_scheduled_for' => 'INTEGER UNSIGNED NOT NULL',
                'task_completed_at' => 'INTEGER UNSIGNED',
                'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
                'task_modified_at' => 'INTEGER UNSIGNED',
                'task_deleted_at' => 'INTEGER UNSIGNED',
                'task_json' => 'MEDIUMTEXT'
                )
        ), array(
            'task_view' => (
                "SELECT *,"
                    ." (task_scheduled_for > NOW())"
                    ." AS task_due,"
                    ." (task_completed_at IS NULL OR task_completed_at < NOW())"
                    ." AS task_completed,"
                    ." (task_deleted_at IS NOT NULL)"
                    ." AS task_deleted"
                ." FROM ".$sql->prefixedIdentifier('test_task')
                )
        ), array(
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval',
            'task_due' => 'boolval',
            'task_completed' => 'boolval',
            'task_deleted' => 'boolval'
        ));
	}
    function tasksTable () {
        return new TasksTable($this->sql, array(
            'name' => 'task',
            'columns' => $this->tables['task'],
            'domain' => 'test_',
            'types' => $this->types
        ));
    }
    function tasksView () {
        return new TasksView($this->sql, array(
            'name' => 'task_view',
            'columns' => $this->views['task_view'],
            'primary' => $table,
            'jsonColumn' => 'task_json',
            'domain' => 'test_',
            'types' => $this->types
        ));
    }
}

?>