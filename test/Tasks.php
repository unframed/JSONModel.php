<?php

require_once('test/JSONModelTest.php');

class Task extends JSONMessage {}

class Tasks extends JSONModel {
    function message($map, $encoded=NULL) {
        return new Task($map, $encoded);
    }
}

class TasksView extends JSONModel {}

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
    function table ($name) {
        return array(
            'name' => $name,
            'columns' => $this->tables[$name],
            'domain' => 'test_'
            );
    }
    function view ($name, $table=NULL) {
        $table = ($table === NULL ? $name : $table);
        return array(
            'name' => $name,
            'columns' => $this->views[$name],
            'idColumn' => $table,
            'jsonColumn' => $table.'_json',
            'domain' => 'test_'
            );
    }
    function tasks () {
        return new Tasks($this->sql, $this->types, $this->table('task'));
    }
    function tasksView () {
        return new TasksView($this->sql, $this->types, $this->view('task_view', 'task'));
    }
}

?>