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
- use [SQLAbstract](https://github.com/unframed/SQLAbstract.php) to query SQL safely everywhere
- support PHP 5.3

Credits
---
- [laurentszyster](https://github.com/laurentszyster)
- [badshark](https://github.com/badshark), [JoN1oP](https://github.com/JoN1oP) and [mrcasual](https://github.com/mrcasual) for requirements, code reviews, tests and reports.

Synopis
---
* [Introduction](#introduction)
* [Use Case](#use-cases)
* [Construct](#construct)
* [Table Options](#table-options)
* [Insert](#insert)
* [Fetch By Ids](#fetch-by-ids)
* [View Options](#table-options)
* [Create Or Replace View](#create-or-replace-view)
* [Select And Replace](#select-and-replace)
* [Count And Column](#count-and-column)
* [Update](#update)
* [Delete](#delete)
* [Box And Unbox](#box-and-unbox)
* [Table Classes](#table-classes)
* [The JSON Column](#the-json-column)
* [View Classes](#view-classes)
* [Create Table If Not Exist](#create-table)
* [Add Columns](#add-columns)
* [Iterative Schema Update](#iterative-schema-update)
* [Here Be Dragons](#here-be-dragons)

### Introduction

The `JSONModel` class provides what "*People Love ORMs*" for:

- consistent type casting, eventually boxing arrays in objects;
- serialization and deserialization of non-scalar types, (ie: arrays); 
- an SQL abstraction to guard against SQL injection in user input, by default;
- the opportunity to structure the sources of their data models in PHP classes.

I left out of this class everything else ORMs usually do that sucks their developers in a time-sink and sometimes down the drain : 

- an application prototype or some form of dependency injection
- a side-effect-free method chain that fails to fully cover SQL
- a compiler for another query language than SQL
- an implementation of the active record pattern

The `JSONModel` class is *only* a base for SQL table and view controllers providing methods to safely query relations, eventually boxed as objects, with typed properties (eventually including non-scalar), through an `SQLAbstract` implementation.

There is *just* enough in `JSONModel` and its dependencies to get their applications covered from iterative schema updates through CRUD, paginated search and filter with all JSON types.

With limits and without SQL injections, safely.

### Use Case

The use case of `JSONModel` is the development of a plugin for an existing database application, reusing its database access APIs or bypassing them (ie: running the same PHP code inside and outside a legacy application with different `SQLAbstract` implementations).

The purpose of `JSONModel` is to maintain the legacy of its application and eventually erase its technical debt be it:  a) common and probably unsafe inline SQL; b) an uniquely incomplete API on top of PDO or mysql_* functions;
c) yet another PHP framework, including WordPress; d) a funky mix of all three.

So let's assume a simplistic task scheduler application, with a tasks table and a related tags table in its database.

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

CREATE TABLE IF NOT EXISTS `tags` (
    `tag_task` INTEGER AUTOINCREMENT,
    `tag_label` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`tag_task`, `tag_label`)
);
~~~

Now let's write the simplest function returning a new `JSONModel` object to control this legacy table `tasks`.

### Construct

The constructor of `JSONModel` takes two arguments : an `SQLAbstract` instance and an array of options.

For instance here is a model factory for the `tasks` table defined above :

~~~php
<?php

function tasksTable (SQLAbstract $sql) {
    return new JSONModel($sql, array('name' => 'tasks'));
}

?>
~~~

Note how a new `JSONModel` instance can be constructed without a definition of its columns or its selection, nor knowledge about its primary keys and column types.

For instance, to count all relations in the `tasks` table then select from it all columns and return a list of rows, we can reuse the same function that selects everything from any table or view controlled by a `JSONModel`:

~~~php
<?php

function selectAllTasks(JSONModel $controller) {
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

The name of a database table or view name may be all what an application of `JSONModel` needs to: `count`, `select`, `insert`, `replace`, `update` or `delete` relations in a table. And do it by default only with the safe options supported by  `SQLAbstract`.

### Table Options

To enable other features, the `JSONModel` constructor requires more options :

- `types` a map of column name(s) to PHP callables;
- `primary` a list of the column(s) in the primary key;
- `columns` a map of column names to their SQL type definition for a table, or a SELECT statement for a view;
- `jsonColumn` the name of the column used to store JSON or `NULL`.  

For instance to factor a `JSONModel` for our example's `tasks` table we should define the primary key and the integer columns :

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

With all types casted, which is probably the first loved function of ORMs.

### Insert

For instance to insert some test tasks using integer values :

~~~php
<?php

function insertTestTasks(SQLAbstract $sql) {
    $controller = tasksTable($sql);
    $inserted = array();
    $now = time();
    foreach (array(
        array(
            `task_name` => 'in one hour',
            `task_scheduled_for` => $now + 3600,
            `task_created_at` => $now,
            `task_modified_at` => $now
        ),
        array(
            `task_name` => 'in two hours',
            `task_scheduled_for` => $now + 7200,
            `task_created_at` => $now,
            `task_modified_at` => $now
        )
    ) as $tasks) {
        array_push($inserted, $controller->insert($task));
    }
    return $inserted;
}

?>
~~~

The `insert` method returns the inserted column names and values.

Note that a `task_id` column was not provided.

If the model defined a single column with type 'intval' as primary key, a database identifier is assumed and the returned relation will be updated with the last inserted identifier. Also, in that case, the `insert` method fail il that identifier is set in the relation to insert.   

### Fetch By Ids

To fetch a task by id : 

~~~php
<?php

function fetchTaskById (SQLAbstract $sql, $id) {
    return tasksTables($sql)->fetchById($id);
}

?>
~~~

The `$id` argument may be a scalar value in case of a single primary column or a complete map of primary column names with scalar values otherwise.

There is also a plural form, but it is implemented only for tables with single primary column.

~~~php
<?php

function fetchTaskByIds (SQLAbstract $sql, $ids) {
    return tasksTables($sql)->fetchByIds($ids);
}

?>
~~~

Fetching by ids is all fine, but there's so much more SQL can select.

Some of that SQL power is available, safely, with limits, throught `SQLAsbtract` [query options](https://github.com/unframed/SQLAbstract.php#query-options).

And if anything more complex than an SQL `WHERE` expression is required to select relations, then one or more SQL views should be created.

### View Options

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

Avoid the time-sink of maintaining complicated ORM method chain invocations entangled with hidden SQL queries in procedural PHP code. Instead, let your PHP scripts create complex SQL views that can be used simply. And safely.

And now that we have defined this view let's create it.

### Create Or Replace View

There is not much to write in a `createTasksView` function, though.

~~~php
<?php

function createTasksView (SQLAbstract $sql) {
    return tasksView($sql)->create();
}

?>
~~~

Note that the SQL statement will be equivalent to `CREATE OR REPLACE VIEW`.

Also note that the same `create` method will try to execute the equivalent SQL statement `CREATE TABLE IF NOT EXISTS` for a table model.

### Select and Replace

Now we can count and select messages from a more complex view. 

For instance to reschedule all due tasks in one hour : 

~~~php
<?php

function rescheduleDueInOneHour (SQLAbstract $sql) {
    $tasksView = tasksView($sql);
    $tasksTable = tasksTable($sql);
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
    // loop and replace each tasks.
    foreach($dueTasks as $task) {
        $task['task_scheduled_for'] = $now + 3600;
        $task['task_modified_at'] = $now;
        $tasksTable->replace($task);
    }
}

?>
~~~

Note that, so far in all examples, the type of `$task` is a plain associative array. The `select`, `fetchById` and `fetchByIds` method returns associative arrays. Both `insert` and `replace` functions accepts associative arrays as their first `$message` argument.

Boxing and unboxing is left to be defined by the extension classes, eventually.

### Count and Column

The sources above demonstrate the use one `select` and many atomic `replace`, but there is a better way to update databases. We could have written the same function more efficiently, using `count`, `column` and `update` : 

~~~php
<?php

function rescheduleDueInOneHour (SQLAbstract $sql) {
    $tasksView = tasksView($sql);
    $tasksTable = tasksTable($sql);
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

This demonstration of the possible options of `count`, `column` and `update` is a very common SQL pattern, either to update or delete a previously selected sets of relations at once. But it will break past the limit on SQL parameters set by the database driver(s).

### Update

So we can use only one `update` to implement rescheduling of all due tasks in one hour :

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

In `JSONModel` safe options are asserted by default and the use of a `where` option requires an explicit request.

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

So far we've been playing with a single table. 

What about relations ?

### Relate

Use `relate` to fetch selected sets of related data at once.

For instance to relate the rows in tables `task` and `tags` :

~~~php
<?php

function tagsTable (SQLAbstract $sql) {
    return new JSONModel($sql, array(
        'name' => 'tags',
        'primary' => array(
            'tag_task', 'tag_label'
        ),
        'types' => array(
            'tag_task' => 'intval'
        )
    ));
}

function relateTasksWithTags (SQLAbstract $sql, array $options) {
    return tasksTable($sql)->relate($options, array(
        'task_tags' => tagsTable($sql)
    ));
}

?>
~~~

That's it for CRUD.

Note that we have been so far with only the `JSONModel` class, dynamically defined models and associative arrays.

What about extension classes, objects boxing and unboxing ? 

### Box And Unbox

People love ORMs because they box associative arrays in objects.

To that effect the `JSONModel` class provides one interface - `message($map, $encoded=NULL)` - to eventually box associative arrays with whatever object suites all those lovable people. 

Plus two methods where to unbox the object's arrays: `insert` and `replace`.

~~~php
<?php

class Task extends JSONMessage {}

class TasksModel extends JSONModel {
    // boxing array with Task on select and insert
    function message (array $map, $encoded=NULL) {
        return new Task($map);
    }
    // unboxing arrays from Tasks on insert and replace
    function insert (Task $task) {
        return parent::insert($task->map);
    }
    function replace (Task $task) {
        return parent::replace($task->map);
    }
}

?>
~~~

It is a practical design pattern to define a base class for an application or module's table(s) and view(s), a place where to redefine boxing, insert and replace for all inheritors.

### Table Classes

Practically, controller classes are also good places to define data models  and that's another reason people love ORMs.

For instance a `TasksTable` extending `TasksModel` with a factory for itself and other static methods to define its model's options :

~~~php
<?php

class TasksTable extends TasksModel {
    static function columns (SQLAbstract $sql) {
        return array(
            'task_id' => 'INTEGER NOT NULL AUTO_INCREMENT',
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

Note that I added a JSON column named `task_json` in the `TasksTable` model above, to store non-scalar properties along with a cache of all typed column values.

But before we see how it works, let's add this new column to our legacy table.

### Add Columns

Yet another reason to love ORMs and the likes is their ability to add columns to existing tables.

For instance to add this new JSON column defined by `TasksTable` but missing in its legacy table :

~~~php
<?php

function repairTasksTable ($sql) {
    // list the models to repair
    $models = array(
        TasksTable::factory($sql)
    );
    // return TRUE if columns where added
    return JSONModel::repair($models);
}

?>
~~~

We will see further what else this static `repair` method can do for its applications. 

Adding missing columns is all `repair` will do for table models. 

Changing types, renaming or removing columns from a database full of data is not a very good idea to start with: it is your application's data and semantic model.

Now let's see what this new JSON column can do.

### The JSON Column

When a JSON column is defined and present, the methods `insert` and `replace` will save scalar and non-scalar values in this column as a JSON string.

For instance, with a JSON column defined we can add a, dummy, non-scalar 'document' property, with null as 'root' element. For instance to : 

~~~php
<?php

$now = time();
$task = new Task(array(
    'task_id' => 1, 
    'task_name' => 'in one hour', 
    'task_created_at' => $now,
    'task_scheduled_for' => $now + 3600,
    'document' => array(
        'root' => NULL
    )
));
$tasksTable->replace($task);

?> 
~~~

In the JSON column :

~~~json
{
    "task_name": "in one hour", 
    "task_created_at": 1423819815,
    "task_scheduled_for": 1423823415,
    "document": {
        "root": null
    },
    "task":1
}
~~~

The method `select`, `fetchById` and `fetchByIds` may use the JSON column to merge values from the selected columns with the JSON decoded array.

The `update` does not update the JSON column, use `replace` instead.

For instance to retrieve the first task and add a 'data' array to it :

~~~php
<?php

$task = $tasksTable->fetchById(2);
$task->map['data'] = array(1,2,3);
$tasksTable->replace($task);

?> 
~~~

In the JSON column : 

~~~json
{
    "task_name": "in two hour",
    "task_created_at": 1423819815,
    "task_scheduled_for": 1423827015,
    "data": [1,2,3],
    "task": 2
}
~~~

This JSON column is required to let the method `json` return a list of JSON encoded strings that represent the (eventually consistent) state of the selected relations.

For instance, to get a list of the thirty first tasks, encoded in JSON :

~~~php
<?php

function topTasks (SQLAbstract $sql) {
    return '['.implode(',', tasksTable($sql)->json(array(
        'limit' => 30
    )).']';
}

?> 
~~~

Note that the depth and size of the JSON objects will not affect the speed of this function.

### View Classes

Let's wrap up a `TasksView` class before we finish :

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

Again I added a `jsonColumn` previously added to `TasksTable`, so let's repair.

### Iterative Schema Update

Having opened up the possibility for related classes in independent source files to create tables, add columns and replace views, it is imperative to provide the correct and practical solution to the issue of iterative schema update.

As we have seen above, replacing views is all fine as long as all missing tables and columns have been added.

Replacing views and tables is fasts enough to be done at once, but adding all columns may fail the limit one PHP script execution time in most hosting environment.

~~~php
<?php

function repairTasksModels ($sql) {
    $models = array(
        TasksTable::factory($sql), 
        TasksView::factory($sql) 
    );
    return JSONModel::repair($sql, $models);
}

?>
~~~

That's the function to run everytime the data models defined in `TasksTable` and `TasksView` may have changed. If a column is add the function will ask its caller to iterate. 

Also intermediary views don't have models and must be replace first.

So there is a third optional argument to `repair($sql,$model,$views)`, a simple list of SQL statements and we may have as well:

~~~php
<?php

function repairTasksModelsAndViews ($sql) {
    $models = array(
        TasksTable::factory($sql)
    );
    $views = array(
        'task_view' => TasksView::columns($sql)
    );
    return JSONModel::repair($sql, $models, $views);
}

?>
~~~

Ability to "migrate" databases, checked.

This `repai` method completes the requirements for `JSONModel`.

### Here Be Dragons

Beware that having one PHP class for each table or view can be both a blessing and a curse.

A blessing for the accessibility of the data model sources and for the opportunity of code reuse between classes.

A curse of fossil classes, side effects and bloated dependencies.

