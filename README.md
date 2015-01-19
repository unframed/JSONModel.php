JSONModel.php
===
A simple protocol to map between JSON and SQL, a practical PHP implementation.

The `JSONModel` class implements the Data Mapper pattern to map between SQL relations and `JSONMessage` instances, using an `SQLAbstract` class implementation. 

Definitively not your mother's ORM.

Acting as model controller, `JSONModel` provides methods to insert, replace, update and delete JSON messages in an SQL database.

~~~
insert($values)
replace($values)
update($key, $values)
~~~

Acting as view controller, `JSONModel` also provides methods to fetch one, fetch all, select, filter and count relations from an SQL table or an SQL view.

~~~
fetchById($id)
fetchByIds($ids)
select($options)
filter($options)
count($options)
~~~

...

Requirements
---
...

Synopis
---
...

### Model Controllers

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

class Tasks extends JSONModel {
    function __construct ($sqlAbstract) {
        parent::__construct(
            $sqlAbstract, 'Task', 'task', array(
                'task_name' => 'VARCHAR(255) NOT NULL',
                'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
                'task_modified_at' => 'INTEGER UNSIGNED',
                'task_deleted_at' => 'INTEGER UNSIGNED'
            ), array (
                'task_created_at' => 'intval',
                'task_modified_at' => 'intval',
                'task_deleted_at' => 'intval'
            ));
    }
    function addNew ($map) {
        $task = $this->message($map);
        if ($task->createdAt(time()) !== NULL) {
            throw new Exception(
                "Expected task_created_at to be NULL before insert"
                );
        }
        $this->insert($task);
        return $task;
    }
    function names ($ids=NULL) {
        return array_map(function ($hello) {
            return $hello->name();
        }, $this->fetchAll($ids));
    }
}

?>
~~~

...

### Application

~~~php
<?php

class Application {
    private $sqlAbstract;
    function __construct() {
        $this->sqlAbstract = new SQLAbstractPDO();
    }
    function tasks () {
        return new Tasks($this->sqlAbstract);
    }
}

?>
~~~

...

### Controllers

~~~php
<?php

function insertTask (Tasks $tasks, JSONMessage $message) {
    return $tasks->insert($message);
}

?>
~~~
...

~~~php
<?php

function listTasksNames (Tasks $tasks, JSONMessage $message) {
    return $tasks->names($message->getList('ids'));
}

?>
~~~

...

Requirements
---
...

- support PHP 5.2 and prefixed table names
