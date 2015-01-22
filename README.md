JSONModel.php
===
[![Build Status](https://travis-ci.org/unframed/JSONModel.php.svg)](https://travis-ci.org/unframed/JSONModel.php)

A simple protocol to map between JSON and SQL, a practical PHP implementation.

Requirements
---
- map between JSON messages and SQL relations 
- provide practical conveniences to query tables and views on an extensible controller class
- with readable semantics, ie: with named options as method arguments
- use JSONMessage.php to box results and extend application classes
- use SQLAbstract.php to query, safely by default
- support PHP 5.3

Synopis
---
Let's assume a task scheduler as a database application, with a single tasks table.

So the `JSONModel` class provides

### __construct

Here's what a `Tasks` controller could look like, using and extending `__construct` :

~~~php
<?php

class Tasks extends JSONModel {
    static function columns () {
        return array(
            'task_id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
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
            'task_id' => 'intval',
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval'
            );
    }
    function __construct ($sqlAbstract) {
        parent::__construct($sqlAbstract, self::types(), array(
            'name' => 'task',
            'columns' => self::columns(),
            'primary' => 'task_id'
            ));
    }
}

?>
~~~

The JSONModel constructor can be applied differently, but the pattern of static `columns` and `types` allow to keep columns and type definitions in its controller's class.

### Select and Replace

Here is what the function rescheduling all due tasks in one hour could look like, brutally written as a `select` and a loop around `replace` : 

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // select all due tasks, completely, unsafely with a literal WHERE clause
    $dueTasks = $tasks->select(array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
    // loop and replace each.
    foreach($dueTasks as $task) {
        $map = $task->map;
        $map['task_scheduled_for'] = $now + 3600;
        $map['task_modified_at'] = $now;
        $tasks->replace($task);
    }
}

?>
~~~

This works unseafely and innefficiently, but it demonstrate the use of an arbitrary unsafe `select` and many atomic `replace`.

### Identifiers and Update

We could have written the same function safe and more efficient, using `ids` and `update` : 

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // select the due tasks identifiers
    $dueTaskIds = $tasks->ids(array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
    // update the tasks in that set of identifiers, safely with a filter. 
    $tasks->update(array(
        'task_scheduled_for' => $now + 3600,
        'task_modified_at' => $now
    ), array(
        'filter' => array(
            'task_id' => $dueTaskIds
        )
    ));
}

?>
~~~

### Safe by default

In this example, we may also assert safety and only one `update` to implement rescheduling of all due tasks in one hour :

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // update the tasks in that set of identifiers, at once. 
    $tasks->update(array(
        'task_scheduled_for' => $now + 3600,
        'task_modified_at' => $now
    ), array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
}

?>
~~~

### Model Options

~~~json
{
    "name": "task",
    "columns": []
}
~~~

...

~~~json
{
    "name": "task",
    "columns": [],
    "primary": "task_id",
    "jsonColumn": "task_json",
    "domain": "test_"
}
~~~

### Count and Update Options

...

~~~json
{
    "where": "",
    "params": [],
}
~~~

...

~~~json
{
    "filter": {},
    "like": {}
}
~~~

...

### Select Options

...

~~~json
{
    "columns": [],
    "where": "",
    "params": [],
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

...

~~~json
{
    "columns": [],
    "filter": {},
    "like": {},
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

...

Interface
---
The `JSONModel` class implements the Data Mapper pattern to map between SQL relations and `JSONMessage` instances using an `SQLAbstract` class implementation.

~~~
__construct(SQLAbstract $sql, array $types, array $options):JSONModel
~~~

Acting as model controller, `JSONModel` provides methods to insert, replace and update JSON messages in an SQL database.

~~~
create(array $options):int
insert(JSONMessage $message):JSONMessage
replace(JSONMessage $message):int
update(array $map, array $options=NULL, bool $safe=TRUE):int
delete(array $options=NULL, bool $safe=TRUE):int
~~~

Acting as view controller, `JSONModel` also provides methods to fetch one, fetch all, select, select and count relations from an SQL table or an SQL view.

~~~
ids(array $options, bool $safe=TRUE):array
fetchById(int $id):JSONMessage
fetchByIds(array $ids):array
count(array $options, bool $safe=TRUE):int
select(array $options, bool $safe=TRUE):array
~~~

This simple set of one constructor and ten methods - plus the options they support - together cover a lot of ground for its application.

From basic CRUD to filtered pagination.

