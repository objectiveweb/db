<?php

namespace Objectiveweb\DB;

use PDO;

class Query {

    /** @var \PDOStatement $stmt */
    var $stmt;
    var $sql = null;

    function __construct($stmt) {
        $this->error = null;
        $this->stmt = $stmt;
    }

    /**
     *
     * Binds $value to $pos
     *
     * from http://stackoverflow.com/a/6743773/164469
     *
     * @param $pos string "field"
     * @param $value mixed [value]
     * @param null $type PDO::PARAM_* code
     * @return $this
     */
    public function bind($pos, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }

        $this->stmt->bindValue(":$pos", $value, $type);

        return $this;
    }

    /**
     * Executes the current statement, returns the number of modified rows
     *
     * @param array $bindings [ ":field" => "value", ... ]
     * @throws \PDOException
     * @throws \Exception when an error occurs
     */
    function exec($bindings = null)
    {
        $res = $this->stmt->execute($bindings);

        if ($res !== false) {
            return $this->stmt->rowCount();
        } else {
            throw new \Exception(json_encode($this->stmt->errorInfo()), 500);
        }
    }

    /**
     * Fetches a row from a result set associated with the current Statement.
     *
     * @return array
     */
    function fetch()
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @return array
     */
    function all()
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Returns an associative array with all the result set rows mapped by $field
     * @param string $field the field to index
     */
    function map($field) {
        $map = array();

        while($row = $this->stmt->fetch(PDO::FETCH_ASSOC)) {
            if(!isset($row[$field])) {
                throw new \Exception("Invalid field $field", 500);
            }

            $map[$row[$field]] = $row;
        }

        return $map;
    }


}