JSONModel.php
===
[![Build Status](https://travis-ci.org/unframed/JSONModel.php.svg)](https://travis-ci.org/unframed/JSONModel.php)

"*People Love ORMs*" - [Paul M. Jones](http://auraphp.com/blog/2013/09/30/lessons-learned/). 

Requirements
---
- map between JSON messages, SQL relations and PHP objects
- support composite SQL primary keys, numeric identifiers and a JSON column.
- provide a few methods to query tables and views in one base controller class
- with good names and well named options as first or second argument
- implement iterative schema updates (aka: database repair) 
- use [JSONMessage.php](https://github.com/laurentszyster/JSONMessage.php) to box results and extend application classes
- use [SQLAbstract](https://github.com/unframed/SQLAbstract.php) to query safely and execute everywhere
- support PHP 5.3

Credits
---
To [badshark](https://github.com/badshark), [JoN1oP](https://github.com/JoN1oP) and [mrcasual](https://github.com/mrcasual) for code reviews, tests and reports.

Synopis
---

* [Construct](#construct)
* [Table Options](#table-options)
* [Insert](#insert)
* [View Options](#table-options)
* [Select And Replace](#select-and-replace)
* [Count And Column](#count-and-column)
* [Update](#update)
* [Delete](#delete)
* [Controller And Message Classes](#controller-and-message-classes)
* [Table Classes](#table-classes)
* [The JSON Column](#the-json-column)
* [View Classes](#view-classes)
* [Create](#create)
* [Alter](#alter)
* [Iterative Schema Update](#iterative-schema-update)
* [Here Be Dragons](#here-be-dragons)

Let's assume a task scheduler as a database application, with a single tasks table.

~~~sql
CREATE TABLE IF NOT EXISTS `tasks` (
    `task_id` INTEGER AUTOINCREMENT PRIMARY KEY,
    `task_name` VARCHAR(255) NOT NULL,
    `task_scheduled_for` INTEGER UNSIGNED NOT NULL,
    `task_completed_at` INTEGER UNSIGNED,
    `task_created_at` INTEGER UNSIGNED NOT NULL,
    `task_modified_at` INTEGER UNSIGNED NOT NULL,
    `task_deleted_at` INTEGER UNSIGNED,
    `task_description` MEDIUMTEXT
);
~~~

Now let's write the simplest function returning a new `JSONModel` object to control the legacy table `tasks` defined above.

### Construct

The constructor of `JSONModel` takes two arguments : an `SQLAbstract` instance and an array of options.

For instance :

~~~php
<?php

function tasksTable (SQLAbstract $sql) {
    return new JSONModel($sql, array('name' => 'tasks'));
}

?>
~~~

Note how a new `JSONModel` instance can be constructed without a definition of its columns or its selection, nor knowledge about its primary keys and column types.

The name of a database table or view name may be all what an application of `JSONModel` needs to count, select, insert, replace, update or delete relations in a table. And do it by default only with the safe options supported by  `SQLAbstract`.

For instance, to count all relations in the `task` table then select from it all columns and return a list of `JSONMessage` boxing the results, we can reuse the same function that selects everything from any table or view controlled by a `JSONModel` controller:

~~~php
<?php

function selectAll(JSONModel $controller) {
    $count = $controller->count();
    if ($count) > 0) {
        return array(
            'count' => $count,
            'list' => $controller->select(array(
                'limit' => $count
                ))
            );
    }
    return array('count' => 0);
}

?>
~~~

Look, this `JSONModel` was also made for composition !

More options are required however to cast types, store non-scalar properties, select row(s) by primary key(s), create a table if it does not exist or alter its columns.

### Table Options

To enable other features, the `JSONModel` constructor requires more options :

- `types` a map of column name(s) to PHP callables;
- `primary` a list of the column(s) in the primary key;
- `columns` a map of column names to their SQL type definition for a table, or a SELECT statement for a view;
- `jsonColumn` the name of the column used to sore JSON or `NULL`.  

For instance to factor a `JSONModel` for our example's `task` table we should define the primary key and the integer columns :

~~~php
<?php

function tasksTable (SQLAbstract $sql) {
    return new JSONModel($sql, array(
        'name' => 'tasks',
        'primary' => array(
            'task_id'
        ),
        'types' => array(
            'task_id' => 'intval',
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval'
        )
    ));
}

?>
~~~

Now we can insert, replace and fetch tasks by identifier(s).

With all types casted.

### Insert

...

### View Options

Following the example of `SQLAbstract.php`, applications of `JSONModel.php` must assumes that when anything more complex than an SQL `WHERE` expression is required to select relations then one or more SQL views should be created.

Here, for instance, a view of tasks and various time-related states :

~~~php
<?php

function tasksView (SQLAbstract $sql) {
    return new JSONModel($sql, array(
        'name' => 'tasks_view',
        'columns' => (
            "SELECT *,"
            ." (task_scheduled_for < NOW ()) AS task_due"
            ." (task_completed_at IS NULL) AS task_todo",
            ." (task_due AND task_todo) AS task_overdue",
            ." (task_deleted_at IS NOT NULL) AS task_deleted",
            ." FROM ".$sql->prefixedIdentifier('task')
        ),
        'primary' => array(
            'task_id'
        ),
        'types' => array(
            'task_id' => 'intval',
            'task_scheduled_for' => 'intval',
            'task_completed_at' => 'intval',
            'task_created_at' => 'intval',
            'task_modified_at' => 'intval',
            'task_deleted_at' => 'intval',
            'task_due' => 'boolval',
            'task_todo' => 'boolval',
            'task_overdue' => 'boolval',
            'task_deleted' => 'boolval'
        )
    ));
}

?>
~~~

...

### Select and Replace

Here is what the function rescheduling all due tasks in one hour could look like, brutally written as a `select` and a loop around `replace` : 

~~~php
<?php

function rescheduleDueInOneHour (JSONModel $tasksTable, JSONModel $tasksView) {
    $now = time();
    // set the selection's option
    $options = array(
        'filter' => array(
            'task_due' => TRUE
        )
    );
    // count all due tasks
    $options['limit'] = $tasksView->count($options);
    // select all due tasks
    $dueTasks = $tasksView->select($options);
    // loop and replace each.
    foreach($dueTasks as $task) {
        $map = $task->map;
        $map['task_scheduled_for'] = $now + 3600;
        $map['task_modified_at'] = $now;
        $tasksTable->replace($task);
    }
}

?>
~~~

This demonstrate the use one `select` and many atomic `replace`, but there is a better way to update databases.

### Count and Column

We could have written the same function more efficiently, using `count`, `column` and `update` : 

~~~php
<?php

function rescheduleDueInOneHour (JSONModel $tasksView, JSONModel $tasksTable) {
    $now = time();
    // select all the identifiers of the due tasks.
    $count = $tasksView->count(array(
        'filter' => array('task_due')
    ));
    $dueTaskIds = $tasksView->column(array(
        'columns' => array('task_id'),
        'filter' => array('task_due' => TRUE),
        'limit' => $count,
        'order' => array('task_scheduled_for')
    ));
    // update the tasks in that set of identifiers, safely with a filter. 
    $tasksTable->update(array(
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

This demonstration of the possible options of `count`, `column` and `update` is a very common SQL pattern, either to update or delete a previously selected sets of relations at once.

### Update

We could use only one `update` to implement rescheduling of all due tasks in one hour :

~~~php
<?php

function rescheduleDueInOneHour (JSONModel $tasksTable) {
    $now = time();
    // update the tasks in that set of identifiers, at once. 
    $tasksTable->update(array(
        'task_scheduled_for' => $now + 3600,
        'task_modified_at' => $now
    ), array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
}

?>
~~~

Note the `FALSE` safety flag as third argument in the call to `update`.

In `JSONModel`, by default, safe options are asserted and the use `where` option requires an explicit request. 

### Delete

Deleting all due tasks is as simple and as problematic should user input be passed instead of a safe SQL expression :

~~~php
<?php

function deleteDue (JSONModel $tasksTable) {
    // update the tasks in that set of identifiers, at once. 
    $tasksTable->delete(array(
        'where' => 'task_scheduled_for < NOW()'
    ), FALSE);
}

?>
~~~

Note that the type hint to `JSONModel`.

We have been so far with the "stock" controller class, dynamically defined models and the default `JSONMessage` class.

### Controller And Message Classes

The `JSONModel` and `JSONMessage` classes can be extended.

For instance, let's extend a new `TasksModel` class from `JSONModel`, one that yields instances of `Task` instead of `JSONMessage`.  

~~~php
<?php

class Task extends JSONMessage {}

class TasksModel extends JSONModel {
    function message (array $map) {
        return new Task($map);
    }
}

?>
~~~

Practically, controller classes are the right place to define its `JSONModel` options.

### Table Classes

For instance a `TasksTable` extending `TasksModel` with a factory for itself and other static methods to define its options :

~~~php
<?php

class TasksTable extends TasksModel {
    static function columns (SQLAbstract $sql) {
        return array(
            'task_id' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'task_name' => 'VARCHAR(255) NOT NULL',
            'task_scheduled_for' => 'INTEGER UNSIGNED NOT NULL',
            'task_completed_at' => 'INTEGER UNSIGNED',
            'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
            'task_modified_at' => 'INTEGER UNSIGNED',
            'task_deleted_at' => 'INTEGER UNSIGNED',
            'task_json' => 'MEDIUMTEXT'
        );
    }
    static function primary () {
        return array('task_id');
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
    static function factory (SQLAbstract $sql) {
        return new TasksTable ($sql, array(
            'name' => 'tasks',
            'columns' => TasksTable::columns($sql),
            'primary' => TasksTable::primary(),
            'types' => TasksTable::types(),
            'jsonColumn' => 'task_json'
        ));
    }
}

?>
~~~

The constructor of `JSONModel` can be applied differently but this pattern enables to define a model in its controller's class (and ancestors).

### The JSON Column

Note that I added a JSON column named `task_json` in the `TasksTable` model above, to store non-scalar properties along with a cache of all typed column values. When defined and present, the methods `insert` and `replace` will save scalar and non-scalar values in this column as a JSON string.

For instance, with a JSON column defined we can add a non-scalar 'document' property : 

~~~php
<?php

$task = $tasksTable->insert(array('document' => array(
    'root' => NULL
)))

?> 
~~~

Will store this JSON in the `task_json` column:

~~~json
{}
~~~

The method `select`, `fetchById` and `fetchByIds` may use the JSON column to merge values from the selected columns with the JSON decoded array.

For instance to retrieve the first task and add a `data` array to it:

~~~php
<?php

$task = $tasksTable->fetchById(1);
$task->map['data'] = array(1,2,3);
$tasksTable->replace($task);

?> 
~~~

...

~~~json
{}
~~~

This JSON column is required to let the method `json` return a list of JSON encoded strings that represent the (eventually consistent) state of the selected relations.

~~~php
<?php

echo '['.implode(',', $tasksTable->json(array('limit' => 3)).']';

?> 
~~~

And that's fast. 

### View Classes

Avoid the time-sink of maintaining complicated ORM method chain invocations entangled with hidden SQL queries in procedural PHP code. Instead, let your PHP scripts create and use complex SQL views in database.

...

~~~php
<?php

class TasksView extends TasksModel {
    static function columns (SQLAbstract $sql) {
        return (
            "SELECT *,"
            ." (task_scheduled_for < NOW ()) AS task_due"
            ." (task_completed_at IS NULL) AS task_todo",
            ." (task_due AND task_todo) AS task_overdue",
            ." (task_deleted_at IS NOT NULL) AS task_deleted",
            ." FROM ".$sql->prefixedIdentifier('task')
        );
    }
    static function types() {
        return array(
            'task_due' => 'boolval',
            'task_todo' => 'boolval',
            'task_overdue' => 'boolval',
            'task_deleted' => 'boolval'
        );
    }
    static function factory (SQLAbstract $sql) {
        return new TasksView ($sql, array(
            'name' => 'task_view',
            'columns' => TasksView::columns($sql),
            'primary' => TasksTable::primary(),
            'types' => array_merge(
                TasksView::types(),
                TasksTable::types()
            ),
            'jsonColumn' => 'task_json'
        ));
    }
}

?>
~~~

...

### Iterative Schema Update

...

~~~php
<?php

function repair($sql) {
    if (JSONModel::repair(array(
        TasksTable::factory($sql), 
        TasksView::factory($sql) 
        ))) {
        echo 'iterate';
    } else {
        echo 'repaired'
    }
}

?>
~~~

...

### Here Be Dragons

Beware that having one PHP class for each table or view can be both a blessing and a curse.

A blessing for the accessibility of the data model sources and for the opportunity of code reuse between classes.

A curse of fossil classes, side effects and bloated dependencies.

Notes
---
The `JSONModel` class provides what "*People Love ORMs*" for:

- consistent type casting;
- serialization and deserialization of non-scalar types, (ie: arrays); 
- an SQL abstraction to guard against SQL injection in user input, by default;
- the opportunity to structure the sources of their data models in PHP classes.

I left out of this class everything else ORMs usually do that sucks their developers in a time-sink and sometimes down the drain : 

- an application prototype or some form of dependency injection
- a side-effect-free method chain that fails to fully cover SQL
- a compiler for another query language than SQL
- an implementation of the active record pattern

The `JSONModel` class is *only* a base for SQL table and view controllers providing methods to safely query relations as objects (of type `JSONMessage`) with typed properties (eventually including non-scalar), through `SQLAbstract`.

There is enough implemented in that base class and its dependencies to get their applications covered from iterative schema updates through CRUD, paginated search and filter.

Without SQL injections.

