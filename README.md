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
count($options)
~~~

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

?>
~~~

~~~php
<?php

class Tasks extends JSONModel {
    function __construct ($sqlAbstract) {
        parent::__construct(
            $sqlAbstract, 'test', 'task', array(
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
    function message ($map, $encoded=NULL) {
        return new Task($message, $encoded);
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

...

### Application

~~~php
<?php

class Application {
    private $_sql;
    function __construct() {
        $this->_sql = new SQLAbstractPDO();
    }
    function tasks () {
        return new Tasks($this->_sql);
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
