<?php

/**
 * A model controller.
 */
class JSONModel {
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
     *
     */
    protected $types;
    /**
     *
     */
    protected $jsonColumn;
    /**
     *
     */
    protected $primary;
    /**
     *
     */
    protected $isView;
    /**
     *
     */
    function __construct($sql, $types, $options) {
        $this->sql = $sql;
        $this->types = $types;
        $m = new JSONMessage($options);
        $this->name = $m->getString('name');
        $this->columns = $m->getDefault('columns', NULL);
        $this->primary = $m->getString('primary', $this->name);
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
     * Return a new JSONMessage to be used by the model's methods.
     *
     * @param array $map
     * @param string $encoded
     * @return JSONMessage
     */
    function message ($map, $encoded=NULL) {
        return new JSONMessage($map, $encoded);
    }
    /**
     * Return the qualified name of this model (ie: its unprefixed table name)
     *
     * @return string
     */
    function qualifiedName () {
        return $this->domain.$this->name;
    }
    /**
     *
     */
    function createViewStatement () {
        return (
            "CREATE OR REPLACE VIEW "
            .$this->sql->prefixedIdentifier($this->qualifiedName())
            ." AS ".$this->columns
            );
    }
    /**
     *
     */
    function createTableStatement () {
        $columns = array();
        foreach ($this->columns as $name => $statement) {
            array_push($columns, (
                $this->sql->identifier($name)
                ." ".$statement
                ));
        }
        return (
            "CREATE TABLE IF NOT EXISTS "
            .$this->sql->prefixedIdentifier($this->qualifiedName())
            ." (\n\t".implode(",\t\n", $columns)."\t)\n"
            );
    }
    /**
     * Create if it does not exists or replace this model's table or view.
     */
    function create () {
        if ($this->isView) {
            return $this->sql->execute($this->createViewStatement());
        } else {
            return $this->sql->execute($this->createTableStatement());
        }
    }
    function column ($options=array(), $safe=TRUE) {
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
    function ids ($options=array(), $safe=TRUE) {
        $options['columns'] = array($this->primary);
        return $this->column($options, $safe);
    }
    function json ($options=array(), $safe=TRUE) {
        $options['columns'] = array($this->jsonColumn);
        return $this->column($options, $safe);
    }
    /**
     * Cast a row into a map using the types defined for this model.
     *
     * @param array $row
     * @return JSONMessage
     */
    function cast ($row, $map) {
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
    function map ($row) {
        if ($this->jsonColumn !== NULL && array_key_exists($this->jsonColumn, $row)) {
            $encoded = $row[$this->jsonColumn];
            $map = json_decode($encoded, TRUE);
            return $this->message($this->cast($row, $map), $encoded);
        } else {
            return $this->message($this->cast($row, array()));
        }
    }
    /**
     *
     */
    function fetchById ($id) {
        return $this->map($this->sql->getRowById(
            $this->qualifiedName(), $this->primary, $id
            ));
    }
    /**
     *
     */
    function fetchByIds ($ids) {
        $rows = $this->sql->getRowsByIds(
            $this->qualifiedName(), $this->primary, $ids
            );
        return array_map(array($this, 'map'), $rows);
    }
    /**
     * Return the ordered and limited set of relations selected by $options,
     * mapped in a list of messages.
     *
     * @param array $options
     * @return int
     */
    function select ($options=array(), $safe=TRUE) {
        $rows = $this->sql->select($this->qualifiedName(), $options, $safe);
        return array_map(array($this, 'map'), $rows);
    }
    /**
     * Return the count of rows in the table or in a set selected by $options.
     *
     * @param array $options
     * @return int
     */
    function count ($options=array(), $safe=TRUE) {
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
     * Insert a message's into this model's table, return the inserted ID and
     * maybe update the JSON column if it is defined.
     *
     * @param JSONMessage $message
     * @return integer
     */
    function insert ($message) {
        if ($this->isView) {
            throw $this->exception('Cannot insert in a view');
        }
        // check that the new message does not have a primary key set.
        if ($message->has($this->primary)) {
            throw $this->exception('Cannot insert with an identifier set');
        }
        // insert existing columns and save the inserted id, eventually typed
        $table = $this->qualifiedName();
        if ($this->columns === NULL) {
            $map = self::_filterScalarAndNull($message->map);
        } else {
            $map = array_intersect_key($message->map, $this->columns);
        }
        $id = $this->sql->insert($table, $map);
        if (array_key_exists($this->primary, $this->types)) {
            $id = call_user_func_array($this->types[$this->primary], array($id));
        }
        // update the message's map
        $message->map[$this->primary] = $id;
        if ($this->jsonColumn !== NULL) {
            // eventually update the *_json column in the database
            $this->sql->update($table, array(
                $this->jsonColumn => json_encode($message->map)
                ), array('filter' => array($this->primary => $id)));
        }
        // return the updated message
        return $message;
    }
    /**
     * Map a message's map into a row, eventually encoding a JSON column if it
     * exists in the model.
     *
     * @param array $map
     * @param string $jsonColumn
     * @param array $columns
     * @return array
     */
    static function row ($map, $jsonColumn, $columns) {
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
     * Replace a message's into this model's table, return the number of affected rows.
     *
     * @param JSONMessage $message
     * @return int
     */
    function replace ($message) {
        if ($this->isView) {
            throw $this->exception('Cannot replace in a view');
        }
        // check that an identifier is set
        if (!$message->has($this->primary)) {
            throw $this->exception('Cannot replace without an identifier set');
        }
        // replace the whole message's map, maybe serialize in the *_json column
        return $this->sql->replace(
            $this->qualifiedName(), self::row(
                $message->map, $this->jsonColumn, $this->columns
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
     * @return int
     */
    function update ($values, $options=NULL, $safe=TRUE) {
        if ($this->isView) {
            throw $this->exception('Cannot update in a view');
        }
        // supply the default options: update by primary key
        if ($options === NULL) {
            if (!array_key_exists($this->primary, $values)) {
                throw $this->exception('Cannot update without an identifier');
            }
            $options = array(
                'filter' => array(
                    $this->primary => $values[$this->primary]
                    )
                );
            unset($values[$this->primary]);
        } elseif (array_key_exists($this->primary, $values)) {
            throw $this->exception('Cannot set the primary key');
        }
        // update a set of $values in this model's table for the relations selected
        // by the options.
        return $this->sql->update(
            $this->qualifiedName(), $values, $options, $safe
            );
    }
    /**
     * Delete rows from this model's table using select options and return
     * the number of affected rows.
     *
     * @param array $options
     * @return int
     */
    function delete ($options, $safe=TRUE) {
        if ($this->isView) {
            throw $this->exception('Cannot delete from view');
        }
        return $this->sql->delete($this->qualifiedName(), $options, $safe);
    }
}
