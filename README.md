JSONModel.php
===
[![Build Status](https://travis-ci.org/unframed/JSONModel.php.svg)](https://travis-ci.org/unframed/JSONModel.php)

From basic CRUD to filtered pagination, safely by default.

Requirements
---
- map between JSON messages and SQL relations 
- support composite SQL primary keys and non-scalar properties of JSON messages
- provide a few methods to query tables and views in one controller class
- with good names and well named options as first or second argument
- use JSONMessage.php to box results and extend application classes
- use SQLAbstract.php to query, safely by default
- support PHP 5.3

Synopis
---
Let's assume a task scheduler as a database application, with a single tasks table.

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
            'primary' => array('task_id')
            ));
    }
}

?>
~~~

The JSONModel constructor can be applied differently, but the pattern of static `columns` and `types` allow to keep columns and type definitions in its controller's class.

#### Model Options

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
    "primary": ["task_id"],
    "jsonColumn": "task_json",
    "domain": "test_"
}
~~~

...

### Create

~~~php
<?php

// connect to a test database using SQLAbstract
$sql = SQLAbstractPDO:sqlite();
// get a new Tasks controller on the 'task' table
$tasks = new Tasks($sql);
// create the table 'task' if it does not exists
$tasks->create();

?>
~~~

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

This demonstrate the use one `select` and many atomic `replace`, but there is a better way to update databases.

### Column and Update

We could have written the same function more efficiently, using `column` and `update`, but only for the first 30 due tasks by order of scheduled time : 

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // select the identifiers of the due tasks.
    $dueTaskIds = $tasks->column(array(
        'where' => 'task_scheduled_for < NOW()',
        'limit' => 30,
        'order' => array(
            'task_scheduled_for'
            )
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

But that demonstration of the built-in limits and the possible options of `column` and `update` is too far-fetched.

### Update

We can use only one `update` to implement rescheduling of all due tasks in one hour :

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

### Delete

Deleting all due tasks is as simple :

~~~php
<?php

function deleteDue ($tasks) {
    $now = time();
    // update the tasks in that set of identifiers, at once. 
    $tasks->delete(array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
}

?>
~~~

...

### Unsafe Options

...

~~~json
{
    "where": "",
    "params": [],
}
~~~

...

### Safe Options

...

~~~json
{
    "filter": {},
    "like": {}
}
~~~

...

### Select and Column Options

...

~~~json
{
    "columns": [],
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

...
