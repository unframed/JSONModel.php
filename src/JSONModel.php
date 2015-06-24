<?php
/**
 * @version 0.8
 * @todo more test and documentation
 * @copyright Laurent Szyster 2014 - 2015
 * @author laurentszyster@gmail.com
 */

/**
 * A model controller.
 */
class JSONModel {
    /**
     * This model controller's SQL Abstraction Layer
     *
     * @var SQLAbstract $sqlAbstract
     */
    protected $sql;
    /**
     * This model controller's application domain name.
     *
     * @var string $domain
     */
    protected $domain;
    /**
     * This model controller's name
     *
     * @var string $name
     */
    protected $name;
    /**
     * This model controller's columns
     *
     * @var array $columns
     */
    protected $columns;
    /**
     * @var array $types
     */
    protected $types;
    /**
     * @var string $primary
     */
    protected $jsonColumn;
    /**
     * @var array $jsonColum,
     */
    protected $primary;
    /**
     * @var bool isView
     */
    protected $isView;
    /**
     * @var array keys
     */
    protected $keys;
    /**
     * @var array foreign
     */
    protected $foreign;
    /**
     * @var array references
     */
    protected $references;
    /**
     *
     */
    function __construct(SQLAbstract $sql, array $options) {
        $this->sql = $sql;
        $m = new JSONMessage($options);
        $this->name = $m->getString('name');
        $this->columns = $m->getDefault('columns', NULL);
        $this->primary = $m->getList('primary', array($this->name));
        $this->foreign = $m->getMap('foreign', array());
        $this->references = $m->getMap('references', array());
        $this->keys = $m->getList('keys', array());
        $this->domain = $m->getString('domain', '');
        $this->isView = (is_string($this->columns));
        if ($m->has('jsonColumn')) {
            $this->jsonColumn = $m->getString('jsonColumn');
        } elseif (
            is_array($this->columns)
            && array_key_exists($this->name.'_json', $this->columns)
            ) {
            $this->jsonColumn = $this->name.'_json';
        } else {
            $this->jsonColumn = NULL;
        }
        $this->types = $m->getMap('types', array());
    }
    /**
     * Return a new exception to be throwed by the model's methods.
     *
     * @param string $message
     * @param Exception $previous
     * @return Exception
     */
    function exception ($message, $previous=NULL) {
        return new Exception($message, $previous);
    }
    /**
     * Eventually box new message, return the associative array by default.
     *
     * @param array $map
     * @param string $encoded
     * @return JSONMessage
     */
    function message (array $map, $encoded=NULL) {
        return $map;
    }
    /**
     * Return the qualified name of this model (ie: its unprefixed table name)
     *
     * @return string
     */
    final function qualifiedName () {
        return $this->domain.$this->name;
    }
    final static function constraints (SQLAbstract $sql, array $primary, array $foreign) {
        $constraints = array(
            "PRIMARY KEY (".$sql->columns($primary).")"
        );
        foreach ($foreign as $table => $keys) {
            list($columns, $references) = $keys;
            $constraints[] = (
                "FOREIGN KEY (".$sql->columns($columns).")"
                ." REFERENCES ".$sql->prefixed($table)
                ." (".$sql->columns($references).")"
                ." ON DELETE CASCADE ON UPDATE CASCADE"
            );
        }
        return $constraints;
    }
    /**
     * Create if it does not exists or replace this model's table or view.
     */
    final function create () {
        $sql = $this->sql;
        if ($this->isView) {
            return $sql->execute($sql->createViewStatement(
                $this->qualifiedName(), $this->columns
            ));
        } else {
            $statement = $sql->createTableStatement(
                $this->qualifiedName(), $this->columns, JSONModel::constraints(
                    $this->sql, $this->primary, $this->foreign
                )
            );
            // MySQL maintains referential integrity with the InnoDB engine only
            if ($sql->driver() === 'mysql' && count($this->foreign) > 0) {
                $statement = $statement.' TYPE=INNODB';
            }
            return $sql->execute($statement);
        }
    }
    final function column (array $options=array(), $safe=TRUE) {
        if (!array_key_exists('columns', $options)) {
            $options['columns'] = array($this->primary);
        }
        $results = $this->sql->column($this->qualifiedName(), $options, $safe);
        $column = $options['columns'][0];
        if (array_key_exists($column, $this->types)) {
            return array_map($this->types[$column], $results);
        }
        return $results;
    }
    final function ids (array $options=array(), $safe=TRUE) {
        $options['columns'] = $this->primary;
        if (count($this->primary) === 1) {
            return $this->column($options, $safe);
        } elseif (count($this->primary) > 1) {
            return $this->select($options, $safe);
        } else {
            throw $this->exception(
                "No primary key(s) defined for model with name ".$this->name
                );
        }
    }
    final function json (array $options=array(), $safe=TRUE) {
        $options['columns'] = array($this->jsonColumn);
        return $this->column($options, $safe);
    }
    /**
     * Cast a row into a map using the types defined for this model.
     *
     * @param array $row
     * @param array $map
     * @return array
     */
    final function cast (array $row, array $map) {
        foreach ($row as $column => $value) {
            if ($column != $this->jsonColumn) {
                if (array_key_exists($column, $this->types) && $value !== NULL) {
                    $map[$column] = call_user_func_array(
                        $this->types[$column], array($row[$column])
                        );
                } else {
                    $map[$column] = $row[$column];
                }
            }
        }
        return $map;
    }
    /**
     * Map a row into a message, eventually using this model's JSON column if
     * it has been defined.
     *
     * @param array $row
     * @return JSONMessage
     */
    final function map (array $row) {
        if ($this->jsonColumn !== NULL && array_key_exists($this->jsonColumn, $row)) {
            $encoded = $row[$this->jsonColumn];
            $map = json_decode($encoded, TRUE);
            return $this->message($this->cast(
                $row, ($map === NULL ? array(): $map)
            ), $encoded);
        } else {
            return $this->message($this->cast($row, array()));
        }
    }
    final private function assertIdNotScalarOrNull ($id) {
        if (is_scalar($id)) {
            return TRUE;
        } elseif (is_null($id)) {
            throw $this->exception("The primary key(s) must scalar, not NULL");
        } else {
            throw $this->exception("The primary key(s) must scalar");
        }
    }
    final private function primaryKeys () {
        return array_combine(
            $this->primary, array_fill(0, count($this->primary), NULL)
            );
    }
    final private function assertPrimary ($keys) {
        return (count($this->primary) === count(array_filter(
            array_values(array_intersect_key($keys, $this->primaryKeys())),
            'is_scalar'
            )));
    }
    /**
     * Fetch and return a relation by its primary key(s).
     *
     * Accepts as `$id` argument a scalar value for model with a single
     * primary key and a map of columns and values for other models.
     *
     * Throws an exception if no `primary` option was defined or if an incomplete
     * or NULL primary key was passed as argument.
     *
     * @param any $id
     * @return any
     * @throws Exception
     */
    final function fetchById ($id) {
        if (count($this->primary) === 1) {
            // check presence of id
            $this->assertIdNotScalarOrNull($id);

            // attempt to fetch row by id
            $row = $this->sql->getRowById($this->qualifiedName(), $this->primary[0], $id);

            // returns the mapped row or false if not found
            return (is_array($row)) ? $this->map($row) : null;

        } elseif (count($this->primary) > 1) {
            if (!JSONMessage::is_map($id)) {
                throw $this->exception(
                    "The \$id must be an array for model ".$this->name
                    );
            } elseif (!$this->assertPrimary($id)) {
                throw $this->exception(
                    "Missing, non scalar or NULL primary key(s) in \$id : "
                    .json_encode($keys)
                    );
            }
            $filter = array_intersect_key($id, $this->primaryKeys());
            if (count($filter) !== count($this->primary)) {
                throw $this->exception(
                    "Missing primary keys ".json_encode(
                        array_keys(array_diff_key($id, $this->primaryKeys()))
                        )." to fetchById from ".$this->name
                    );
            }
            return $this->map($this->sql->select(
                $this->qualifiedName(), array('filter' => $filter)
                ));
        } else {
            throw $this->exception(
                "No primary key(s) defined for model with name ".$this->name
                );
        }
    }
    /**
     * Fetch and return relations by identifiers.
     *
     * Accepts as `$ids` argument a list of scalar value for model with a single
     * primary key.
     *
     * Throws an exception if no `primary` option was defined or if an incomplete
     * or NULL primary key was passed as argument.
     *
     * @param any $id
     * @return any
     * @throws Exception
     */
    final function fetchByIds (array $ids) {
        if (count($this->primary) === 1) {
            $rows = $this->sql->getRowsByIds(
                $this->qualifiedName(), $this->primary[0], $ids
                );
            return array_map(array($this, 'map'), $rows);
        } elseif (count($this->primary) > 1) {
            throw $this->exception(
                "Not implemented for composite primary, use select with filter instead"
                );
        } else {
            throw $this->exception(
                "No primary key(s) defined for model with name ".$this->name
                );
        }
    }
    /**
     * Return the ordered and limited set of relations selected by $options,
     * mapped in a list of messages.
     *
     * @param array $options
     * @param bool $safe
     * @return any
     */
    function select (array $options=array(), $safe=TRUE) {
        $rows = $this->sql->select($this->qualifiedName(), $options, $safe);
        return array_map(array($this, 'map'), $rows);
    }

    static function indexRows ($rows, $column, &$index) {
        foreach ($rows as $row) {
            $index[strval($row[$column])] = $row;
        }
    }

    function index (array $options=array(), $safe=TRUE) {
        $index = array();
        $rows = $this->select($options, $safe);
        if (count($rows) > 0) {
            JSONModel::indexRows($rows, $this->primary[0], $index);
        }
        return $index;
    }

    static function relateRows ($rows, $column, $keyColumn, $valueColumn, &$index) {
        if ($valueColumn === NULL) { // index the whole row
            foreach ($rows as $row) {
                $key = strval($row[$keyColumn]);
                if (array_key_exists($key, $index)) {
                    $index[$key][$column][] = $row;
                } else {
                    $index[$key][$column] = array($row);
                }
            }
        } else {
            foreach ($rows as $row) { // index one column only
                $key = strval($row[$keyColumn]);
                if (array_key_exists($key, $index)) {
                    $index[$key][$column][] = $row[$valueColumn];
                } else {
                    $index[$key][$column] = array($row[$valueColumn]);
                }
            }
        }
    }

    static function relateModels (SQLAbstract $sql, array &$index, array $models) {
        $keys = array_keys($index);
        $count = count($keys);
        foreach($models as $column => $model) {
            $primary = $model->primary;
            $keyColumn = $primary[0];
            $rows = $model->select(array(
                'filter' => array(
                    $keyColumn => $keys
                ),
                'limit' => 0
            ), FALSE);
            if (count($rows) > 0) {
                $valueColumn = (
                    count($rows[0]) === 2 && count($primary === 2)
                ) ? $primary[1] : NULL;
                JSONModel::relateRows($rows, $column, $keyColumn, $valueColumn, $index);
            }
        }
    }

    function relate (array $options, array $models, $safe=TRUE) {
        if (count($this->primary) > 1) {
            throw new Exception('Composite primary key not supported by relate');
        }
        $index = $this->index($options, $safe);
        if (count($index) > 0) {
            JSONModel::relateModels($this->sql, $index, $models);
        }
        return $index;
    }

    /**
     * Return the count of rows in the table or in a set selected by $options.
     *
     * @param array $options
     * @param bool $safe
     * @return int
     */
    function count (array $options=array(), $safe=TRUE) {
        return $this->sql->count($this->qualifiedName(), $options, $safe);
    }
    private static function _filterScalarAndNull ($map) {
        $values = array();
        foreach ($map as $key => $value) {
            if ($value === NULL || is_scalar($value)) {
                $values[$key] = $value;
            }
        }
        return $values;
    }
    /**
     * Insert a relation in this model's table.
     *
     * If a single integer primary key is defined, this method will assume
     * a database identifier.
     *
     * If a JSON column is defined this method will update it.
     *
     * @param any $message
     * @return any
     */
    function insert ($message) {
        if ($this->isView) {
            throw $this->exception('Cannot insert in a view');
        }
        // check that the new message does not have a primary key set.
        if (
            count($this->primary) === 1
            && array_key_exists($this->primary[0], $message)
            ) {
            throw $this->exception('Cannot insert with an identifier set');
        }
        // insert existing columns and save the inserted id, eventually typed
        $table = $this->qualifiedName();
        if ($this->columns === NULL) {
            $map = self::_filterScalarAndNull($message);
        } else {
            $map = array_intersect_key($message, $this->columns);
        }
        $id = $this->sql->insert($table, $map);
        if (count($this->primary) === 1) {
            $idColumn = $this->primary[0];
            if (array_key_exists($idColumn, $this->types)) {
                $id = call_user_func_array($this->types[$idColumn], array($id));
            }
            // update the message's map
            $message[$idColumn] = $id;
            if ($this->jsonColumn !== NULL) {
                // eventually update the *_json column in the database
                $this->sql->update($table, array(
                    $this->jsonColumn => json_encode($message)
                    ), array('filter' => array($idColumn => $id)));
            }
        }
        // return the updated message, eventually boxed
        return $this->message($message);
    }
    /**
     * Map a message's map into a row, eventually encoding a JSON column if it
     * exists in the model.
     *
     * @param array $map
     * @param string $jsonColumn
     * @param any $columns
     * @return array
     */
    final static function row (array $map, $jsonColumn, $columns) {
        if ($jsonColumn === NULL) {
            if ($columns === NULL) {
                return $map;
            }
            return array_intersect_key($map, $columns);
        }
        $row = array();
        $json = array();
        foreach ($map as $column => $value) {
            if ($column != $jsonColumn) {
                if (is_scalar($value) && (
                    !is_array($columns)
                    || array_key_exists($column, $columns)
                    )) {
                    $row[$column] = $value;
                    $json[$column] = $value;
                } else {
                    $json[$column] = $value;
                }
            }
        }
        $row[$jsonColumn] = json_encode($json);
        return $row;
    }
    /**
     * Replace a relation in this model's table, return the number of affected rows.
     *
     * @param any $message
     * @return int
     */
    function replace ($message) {
        if ($this->isView) {
            throw $this->exception('Cannot replace in a view');
        }
        // check that an identifier is set
        if (!$this->assertPrimary($message)) {
            throw $this->exception(
                'Cannot replace without a primary key(s) set'
                );
        }
        // replace the whole message's map, maybe serialize in the *_json column
        return $this->sql->replace(
            $this->qualifiedName(), self::row(
                $message, $this->jsonColumn, $this->columns
                )
            );
    }
    /**
     * Update values in this model's table, either: for the set of relations selected
     * if $options have been provided; or for the single relation identified by
     * a primary key in the given $values, then return the number of affected rows.
     *
     * @param array $values
     * @param array $options
     * @param bool $safe
     * @return int
     */
    function update (array $values, array $options = array(), $safe = true) {
        if ($this->isView) {
            throw $this->exception('Cannot update in a view');
        }
        $primary = $this->primaryKeys();
        if(empty($options)) {
            // require primary key in $values
            if (!$this->assertPrimary($values)) {
                throw $this->exception(
                    "Cannot update a single relation without a primary key"
                );
            }
            // supply options to select one relation
            $options = array(
                'filter' => array_intersect_key($values, $primary)
            );
            // remove primary key values from the update set
            $values = array_diff_key($values, $primary);
        } elseif (count(array_intersect_key($values, $primary)) > 0) {
            // don't UPDATE the primary key, not even part of it !
            throw $this->exception('Cannot update the primary key');
        }
        // update a set of $values in this model's table for the relations selected
        // by the options.
        return $this->sql->update($this->qualifiedName(), $values, $options, $safe);
    }
    /**
     * Delete rows from this model's table using select options and return
     * the number of affected rows.
     *
     * @param array $options
     * @param bool $safe
     * @return int
     */
    function delete (array $options, $safe=TRUE) {
        if ($this->isView) {
            throw $this->exception('Cannot delete from view');
        }
        $sql = $this->sql;
        if (count($this->references) > 0 && $sql->driver() !== 'mysql') {
            $message = new JSONMessage($options);
            $deleted = array_merge(
                array($this->qualifiedName()), array_keys($this->references)
            );
            $joins = SQLAbstract::joins($sql, $this->primary, $this->references);
            list($whereExpression, $whereParams) = $this->whereParams($message);
            return $sql->execute((
                "DELETE ".implode(", ", array_map(
                    array($sql, 'prefixed'), $deleted
                ))
                ." FROM ".$this->prefixed($table)
                .implode('', $joins)
                ." WHERE ".$whereExpression
            ), $whereParams);
        }
        return $sql->delete($this->qualifiedName(), $options, $safe);
    }

    function temporary ($name, array $options, $safe=TRUE) {
        return $this->sql->temporary(
        	$this->domain.$name, $this->qualifiedName(), $options, $safe
    	);
    }

    /**
     * Return a count and a JSONModel for a temporary selection.
     *
     * Use subset to create temporary tables from a model's table or view,
     * count it and then access it with all the conveniences of JSONModel.
     *
     * @param string $name
     * @param array $options
     * @param bool $safe
     * @return array ($count, $model)
     */
    function subset ($name, array $options, $safe=TRUE) {
        $count = $this->temporary($name, $options, $safe);
        $m = new JSONMessage($options);
        $selected = $m->getList('columns', array());
        if (!empty($selected)) {
            if ($this->isView) {
                $columns = array_flip($selected); // dummy columns definition
            } else {
                $columns = array_intersect_key($this->columns, array_flip($selected));
            }
        } elseif ($this->isView) {
        	$column = NULL; // no column ,-)
        } else {
            $columns = $this->columns; // all columns
        }
        $primary = (assertPrimary(array_keys($columns)) ? $this->primary : NULL);
        return array($count, new JSONModel(array(
            'name' => $name,
            'columns' => $columns,
            'primary' => $primary,
            'types' => $this->types,
            'jsonColumn' => $this->jsonColumn,
            'domain' => $this->domain
        )));
    }

    // how JSONModel database tables and views get created and upgraded: iteratively.

    final static function repairSchemaIter (
        $sql, $primary, $foreign, $tables, $keys, $views, $domain, $iter=TRUE
    ) {
        $addedColumnNames = array();
        $addedConstraints = array();
        $createdTableNames = array();
        $createdIndexNames = array();
        $replacedViewNames = array();
        $exist = $sql->showTables($domain);
        foreach ($tables as $table => $columns) {
            $prefixedName = $sql->prefix($table);
            if (in_array($prefixedName, $exist)) {
                $newColumns = array_diff_key($columns, $sql->showColumns($table));
                if (count($newColumns) > 0) {
                    $sql->execute($sql->alterTableStatement($table, $newColumns));
                    $addedColumnNames = array_merge($addedColumnNames, array_keys($newColumns));
                    if ($iter === TRUE) {
                        return array(
                            'complete' => FALSE,
                            'added' => $addedColumnNames,
                            'created' => $createdTableNames,
                            'foreign' => $addedConstraints,
                            'indexed' => $createdIndexNames,
                            'replaced' => $replacedViewNames
                        );
                    }
                }
            } else {
                $sql->execute($sql->createTableStatement(
                    $table, $columns, JSONModel::constraints(
                        $sql, $primary[$table], array()
                    )
                ));
                array_push($createdTableNames, $table);
            }
        }
        foreach ($foreign as $table => $foreignKeys) {
            $constraints = array();
            $exist = $sql->showForeign($table);
            foreach ($foreignKeys as $referenced => $references) {
                $fqn = $sql->prefix($referenced);
                if (!in_array($fqn, $exist)) {
                    $slug = substr(sha1($table.'_'.$referenced), 0, 6);
                    $constraints[$table.'_'.$slug] = (
                        "FOREIGN KEY (".$sql->columns($references[0]).")"
                        ." REFERENCES ".$sql->prefixed($referenced)
                        ."(".$sql->columns($references[1]).")"
                        ." ON DELETE CASCADE ON UPDATE CASCADE"
                    );
                }
            }
            if (count($constraints) > 0) {
                $sql->execute($sql->alterTableStatement($table, array(), $constraints));
                $addedConstraints = array_merge(
                    $addedConstraints, array_keys($constraints)
                );
                if ($iter === TRUE) {
                    return array(
                        'complete' => FALSE,
                        'added' => $addedColumnNames,
                        'created' => $createdTableNames,
                        'foreign' => $addedConstraints,
                        'indexed' => $createdIndexNames,
                        'replaced' => $replacedViewNames
                    );
                }
            }
        }
        foreach ($keys as $table => $indexes) {
            $indexed = $sql->showIndexes($table);
            foreach ($indexes as $columns) {
                $slug = substr(sha1(json_encode($columns)), 0, 6);
                $fqn = $sql->prefix($table.'_'.$slug);
                if (!(in_array($fqn, $indexed))) {
                    $sql->execute($sql->createIndexStatement($table, $columns, $slug));
                    if ($iter === TRUE) {
                        return array(
                            'complete' => FALSE,
                            'added' => $addedColumnNames,
                            'foreign' => $addedConstraints,
                            'created' => $createdTableNames,
                            'indexed' => $createdIndexNames,
                            'replaced' => $replacedViewNames
                        );
                    }
                }
            }
        }
        foreach ($views as $view => $select) {
            $sql->execute($sql->createViewStatement($view, $select));
            if (in_array($sql->prefix($table), $exist)) {
                array_push($replacedViewNames, $view);
            }
        }
        return array(
            'complete' => TRUE,
            'added' => $addedColumnNames,
            'foreign' => $addedConstraints,
            'created' => $createdTableNames,
            'indexed' => $createdIndexNames,
            'replaced' => $replacedViewNames
        );
    }

    // infer a schema of primary keys, tables and views from a list JSONModel
    // (... and preliminary SQL views ,-)

    final static function schema ($models, $views) {
        $primary = array();
        $foreign = array();
        $tables = array();
        $keys = array();
        foreach($models as $controller) {
            $name = $controller->qualifiedName();
            if ($controller->isView) {
                $views[$name] = $controller->columns;
            } else {
                $primary[$name] = $controller->primary;
                if (count($controller->foreign) > 0) {
                    $foreign[$name] = $controller->foreign;
                }
                $tables[$name] = $controller->columns;
                if (count($controller->keys) > 0) {
                    $keys[$name] = $controller->keys;
                }
            }
        }
        return array($primary, $foreign, $tables, $keys, $views);
    }

    // create or repair the database for a set of models, iteratively.

    final static function repair ($sql, $models, $views=array(), $domain='') {
        list($primary, $foreign, $tables, $keys, $views) = self::schema($models, $views);
        return self::repairSchemaIter(
            $sql, $primary, $foreign, $tables, $keys, $views, $domain, FALSE
        );
    }

}
