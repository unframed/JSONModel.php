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

    // how JSONModel database tables and views get created and upgraded, iteratively.

    final static function repairSchemaIter (
        $sql, $primary, $tables, $views, $domain, $iter=TRUE
    ) {
        $createdTableNames = array();
        $exist = $sql->showTables($domain);
        foreach ($tables as $table => $columns) {
            $prefixedName = $sql->prefix($table);
            if (in_array($prefixedName, $exist)) {
                $newColumns = array_diff_key($columns, $sql->showColumns($table));
                if (count($newColumns) > 0) {
                    $sql->execute($sql->alterTableStatement($table, $newColumns));
                    if ($iter === TRUE) {
                        return array(
                            'complete' => FALSE,
                            'added' => array_keys($newColumns),
                            'created' => $createdTableNames
                        );
                    }
                }
            } else {
                $sql->execute($sql->createTableStatement(
                    $table, $columns, $primary[$table]
                ));
                array_push($createdTableNames, $table);
            }
        }
        foreach ($views as $view => $select) {
            $sql->execute($sql->createViewStatement($view, $select));
        }
        return array(
            'complete' => TRUE,
            'added' => array(),
            'created' => $createdTableNames
        );
    }

    // infer a schema of primary keys, tables and views from a list JSONModel
    // (... and preliminary SQL views ,-)

    final static function schema ($models, $views) {
        $primary = array();
        $tables = array();
        foreach($models as $controller) {
            $name = $controller->qualifiedName();
            if ($controller->isView) {
                $views[$name] = $controller->columns;
            } else {
                $primary[$name] = $controller->primary;
                $tables[$name] = $controller->columns;
            }
        }
        return array($primary, $tables, $views);
    }

    // create or repair the database for a set of models, iteratively.

    final static function repair ($sql, $models, $views=array(), $domain='') {
        list($primary, $tables, $views) = self::schema($models, $views);
        return self::repairSchemaIter($sql, $primary, $tables, $views, $domain, FALSE);
    }

    /**
     * This model controller's SQL Abstraction Layer
     *
     * @var SQLAbstract $sqlAbstract
     */
    protected $sqlAbstract;
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
     * @var array indexes
     */
    protected $indexes;
    /**
     *
     */
    function __construct(SQLAbstract $sql, array $options) {
        $this->sql = $sql;
        $m = new JSONMessage($options);
        $this->name = $m->getString('name');
        $this->columns = $m->getDefault('columns', NULL);
        $this->primary = $m->getList('primary', array($this->name));
        $this->indexes = $m->getList('indexes', array());
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
            return $sql->execute($sql->createTableStatement(
                $this->qualifiedName(), $this->columns, $this->primary
                ));
        }
    }
    final function column (array $options=array(), $safe=TRUE) {
        if (!array_key_exists('columns', $options)) {
            $options['columns'] = array($this->primary);
        }
        $results = $this->sql->column($this->qualifiedName(), $options, $safe);
        $column = $options['columns'][0];
        if (array_key_exists($column, $this->types)) {
            return array_map($types[$column], $results);
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
            return ($row !== false) ? $this->map($row) : $row;

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
    function update (array $values, $options=NULL, $safe=TRUE) {
        if ($this->isView) {
            throw $this->exception('Cannot update in a view');
        }
        // supply the default options: update by primary key
        if ($options === NULL) {
            if (!$this->assertPrimary($values)) {
                throw $this->exception("Cannot update without a primary key(s)");
            }
            $options = array(
                'filter' => array_intersect_key($values, $this->primaryKeys())
                );
        } elseif (count(array_intersect_key($values, $this->primaryKeys())) > 0) {
            throw $this->exception('Cannot set the primary key(s)');
        }
        // update a set of $values in this model's table for the relations selected
        // by the options.
        return $this->sql->update(
            $this->qualifiedName(),
            array_diff_key($values, $this->primary),
            $options,
            $safe
            );
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
        return $this->sql->delete($this->qualifiedName(), $options, $safe);
    }

}
