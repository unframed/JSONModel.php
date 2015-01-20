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
        $this->columns = $m->getDefault('columns', array());
        $this->primary = $m->getString('primary', $this->name);
        $this->jsonColumn = $m->getString('jsonColumn', $this->name.'_json');
        $this->domain = $m->getString('domain', '');
        $this->isView = (is_string($this->columns));
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
    function createStatement () {
        if ($this->isView) {
            return (
                "CREATE OR REPLACE VIEW "
                .$this->sql->prefixedIdentifier($this->qualifiedName())
                ." AS ".$this->columns
                );
        } else {
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
    }
    function create () {
        $this->sql->execute($this->createStatement());
    }
    /**
     * Cast a row into a map using the type caster defined for this model.
     *
     * @param array $row
     * @return JSONMessage
     */
    function cast ($row, $map) {
        foreach ($row as $column => $value) {
            if (array_key_exists($column, $this->types)) {
                $map[$column] = call_user_func_array(
                    $this->types[$column], array($row[$column])
                    );
            } else {
                $map[$column] = $row[$column];
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
        if (array_key_exists($this->jsonColumn, $row)) {
            $encoded = $row[$this->jsonColumn];
            $map = json_decode($encoded, TRUE);
            unset($row[$this->jsonColumn]);
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
     *
     */
    function select ($options=array()) {
        $rows = $this->sql->select($this->qualifiedName(), $options);
        return array_map(array($this, 'map'), $rows);
    }
    /**
     *
     */
    function count ($options=array()) {
        return $this->sql->count($this->qualifiedName(), $options);
    }
    /**
     * Map a message's map into a row, eventually encoding a JSON column if it
     * exists in the model.
     *
     * @param array $map
     * @return array
     */
    function row ($map) {
        if ($this->isView) {
            throw $this->exception('Cannot insert nor replace in an SQL view');
        }
        if ($this->jsonColumn === NULL) {
            return array_intersect_key($map, $this->columns);
        }
        $row = array();
        $json = array();
        foreach ($map as $column => $value) {
            if ($column != $this->jsonColumn) {
                if (is_scalar($value) && array_key_exists($column, $this->columns)) {
                    $row[$column] = $value;
                    $json[$column] = $value;
                } else {
                    $json[$column] = $value;
                }
            }
        }
        $row[$this->jsonColumn] = json_encode($json);
        return $row;
    }
    /**
     * Insert a message's into this model's table, return the inserted ID.
     *
     * @param JSONMessage $message
     * @return integer
     */
    function insert ($message) {
        // check that the new message does not have a primary key set.
        if ($message->has($this->primary)) {
            throw $this->exception('Cannot insert with an identifier set');
        }
        // insert existing columns and save the inserted id
        $table = $this->qualifiedName();
        $id = intval($this->sql->insert(
            $table, array_intersect_key($message->map, $this->columns)
            ));
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
     * Insert a message's into this model's table, return the number of affected rows.
     *
     * @param JSONMessage $message
     * @return integer
     */
    function replace ($message) {
        // check that an identifier is set
        if (!$message->has($this->primary)) {
            throw $this->exception('Cannot replace without an identifier set');
        }
        // replace the whole message's map, maybe serialize in the *_json column
        return $this->sql->replace(
            $this->qualifiedName(), $this->row($message->map)
            );
    }
    function update ($values, $options=NULL) {
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
        }
        // don't set the primary key, ever !
        unset($values[$this->primary]);
        // update a set of $values in this model's table for the relations selected
        // by the options.
        return $this->sql->update(
            $this->qualifiedName(), $values, $options
            );
    }
}
