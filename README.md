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
fetchById(int $id):JSONMessage
fetchByIds(array $ids):array
select(array $options):array
count(array $options):int
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
