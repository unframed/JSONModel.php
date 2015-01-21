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
...

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // select all due tasks, completely
    $dueTasks = $tasks->select(array(
        'where' => 'task_scheduled_for < NOW()'
    ));
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

...

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    // select the due tasks identifiers
    $dueTaskIds = $tasks->ids(array(
        'where' => 'task_scheduled_for < NOW()'
    ));
    // update the tasks in that set of identifiers. 
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

...

~~~php
<?php

function rescheduleDueInOneHour ($tasks) {
    $now = time();
    $tasks->update(array(
        'task_scheduled_for' => $now + 3600,
        'task_modified_at' => $now
    ), array(
        'where' => 'task_scheduled_for < NOW()'
    ));
}

?>
~~~

The `JSONModel` class implements the Data Mapper pattern to map between SQL relations and `JSONMessage` instances using an `SQLAbstract` class implementation.

~~~
__construct(SQLAbstract $sql, array $types, array $options):JSONModel
~~~

Acting as model controller, `JSONModel` provides methods to insert, replace and update JSON messages in an SQL database.

~~~
insert(JSONMessage $message):JSONMessage
replace(JSONMessage $message):int
update(array $map, array $options):int
delete(array $options):int
~~~

Acting as view controller, `JSONModel` also provides methods to fetch one, fetch all, select, select and count relations from an SQL table or an SQL view.

~~~
count(array $options):int
ids(array $options):array
fetchById(int $id):JSONMessage
fetchByIds(array $ids):array
select(array $options):array
~~~

This simple set of one constructor and eight methods - plus the options they support - together cover a lot of ground for its application.

From basic CRUD to filtered pagination.

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
