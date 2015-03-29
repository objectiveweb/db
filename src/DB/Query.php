<?php

namespace Objectiveweb\DB;

use PDO;

class Query {

    var $stmt;

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
     * @throws \Exception when an error occurs
     */
    function exec($bindings = null)
    {
        $res = $this->stmt->execute($bindings);

        if ($res !== false) {
            return $this->stmt->rowCount();
        } else {

            throw new \Exception(json_encode($this->stmt->errorInfo()), $this->stmt->errorCode());
        }
    }

    /**
     * Fetches a row from a result set associated with the current Statement.
     *
     * @return Array
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




}