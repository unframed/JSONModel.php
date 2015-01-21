JSONModel.php
===
[![Build Status](https://travis-ci.org/unframed/JSONModel.php.svg)](https://travis-ci.org/unframed/JSONModel.php)

A simple protocol to map between JSON and SQL, a practical PHP implementation.

Requirements
---
- map between JSON messages and SQL relations 
- provide practical conveniences to repair tables and views
- use SQLAbstract.php and JSONMessage.php
- support PHP 5.3

Synopis
---
The `JSONModel` class implements the Data Mapper pattern to map between SQL relations and `JSONMessage` instances using an `SQLAbstract` class implementation.

Acting as model controller, `JSONModel` provides methods to insert, replace and update JSON messages in an SQL database.

~~~
insert($message):message
replace($message):int
update($map, $options):int
~~~

Acting as view controller, `JSONModel` also provides methods to fetch one, fetch all, select, filter and count relations from an SQL table or an SQL view.

~~~
fetchById($id):message
fetchByIds($ids):array
select($options):array
count($options):int
~~~

...

~~~json
{
    "where": ""
    "params": [],
    "filter": {},
    "like": {}
}
~~~
...

~~~json
{
    "columns": [],
    "where": ""
    "params": [],
    "filter": {},
    "like": {},
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~
...

### Message

~~~php
<?php

class Task extends JSONMessage {
    function createdAt($time) {
        return $this->setDefault('task_created_at', $time);
    }
    function name () {
        return $this->getString('task_name');
    }
}

?>
~~~

### Table

~~~php
<?php

class Tasks extends JSONModel {
    static function columns () {
        return array(
            'task' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'task_name' => 'VARCHAR(255) NOT NULL',
            'task_scheduled_for' => 'INTEGER UNSIGNED NOT NULL',
            'task_completed_at' => 'INTEGER UNSIGNED',
            'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
            'task_modified_at' => 'INTEGER UNSIGNED',
            'task_deleted_at' => 'INTEGER UNSIGNED'
            );
    }
    static function types() {
        return array(
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
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
    function addNew ($map) {
        $task = $this->message($map);
        if ($task->createdAt(time()) !== NULL) {
            throw $this->exception(
                "Expected task_created_at to be NULL before insert"
                );
        }
        $this->insert($task);
        return $task;
    }
    function names ($ids=NULL) {
        return array_map(function ($hello) {
            return $hello->name();
        }, $this->fetchByIds($ids));
    }
}

?>
~~~

### View

~~~php
<?php

class TasksView extends JSONModel {
    static function columns ($sql) {
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
            'domain' => 'test_'
            ));
    }
}

?>
~~~

...

### Application

~~~php
<?php

class Application {
    public $sql;
    public $tables;
    public $views;
    public $types;
    function __construct($sql) {
        $this->sql = $sql;
        $this->tables = array(
            'task' => Tasks::columns()
        );
        $this->views = array(
            'task_view' => TasksView::columns($sql)
        );
        $this->types = array_merge(
            Tasks::types(),
            TasksView::types()
        );
    }
    function tasks () {
        return new Tasks($this->sql, $this->types);
    }
    function tasksView () {
        return new TasksView($this->sql, $this->types);
    }
}

?>
~~~

...

### Controllers

~~~php
<?php

function insertTask (Application $app, JSONMessage $message) {
    return $app->tasks()->insert($message);
}

?>
~~~
...

~~~php
<?php

function listTasksNames (Application $app, JSONMessage $message) {
    return $app->tasks()->names($message->getList('ids'));
}

?>
~~~

...
