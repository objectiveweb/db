<?php
namespace Objectiveweb;

use PDO;


class DB
{
    /** @var \PDO  */
    public $pdo;

    public $error = null;

    /** @var \PDOStatement $stmt */
    private $stmt;

    function __construct($uri, $username, $password = '', $options = array())
    {

        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        $this->pdo = new PDO($uri, $username, $password, array_merge($defaults, $options));
    }

    /**
     * Prepares a query
     *
     * @param $query
     * @return $this
     */
    function query($query)
    {
        $this->error = null;
        $this->stmt = $this->pdo->prepare($query);

        return $this;
    }

    /**
     *
     * Binds $value to $pos
     *
     * from http://stackoverflow.com/a/6743773/164469
     *
     * @param $pos
     * @param $value
     * @param null $type
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
     * @param array $bindings
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


    /* Transactions ------------------------------------------------ */

    function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    function rollBack()
    {
        return $this->pdo->rollBack();
    }

    function commit()
    {
        return $this->pdo->commit();
    }

    function transaction($callable)
    {
        $this->beginTransaction();

        try {
            call_user_func($callable, $this);
            return $this->commit();
        } catch (\Exception $ex) {
            $this->rollBack();
            $this->error = $ex;
            return false;
        }
    }


    /* sql helpers  ------------------------------------------------ */

    /**
     * Performs a SELECT Query
     * @param $table
     * @param $where array [ field => value ] or string
     * @param array $params
     * @return $this
     * @throws \Exception
     */
    function select($table, $where = null, $params = array())
    {

        $defaults = [
            'fields' => '*'
        ];

        $params = array_merge($defaults, $params);

        // TODO implementar JOIN
        if(is_array($table)) {
            throw new \Exception('not implemented');
        }

        list($where, $bindings) = DB\Util::where($where);

        $sql = sprintf("SELECT %s FROM `%s` %s",
            $params['fields'],
            $table,
            !empty($where) ? 'WHERE '.$where : '');

        $this->query($sql);

        $this->exec($bindings);

        return $this;
    }

    /**
     * Inserts $data into $table
     *
     * @param $table
     * @param $data array [ field => value, ... ]
     * @return $id int Last Insert ID or NULL if no rows where changed
     */
    function insert($table, $data)
    {

        $fields = array_keys($data);

        $sql = "INSERT INTO " . $table . " (" . implode($fields, ", ") . ") VALUES (:" . implode($fields, ", :") . ");";

        $this->query($sql);
        foreach ($fields as $field) {
            $this->bind($field, $data[$field]);
        }

        $rows = $this->exec();

        return ($rows === 0) ? NULL : $this->pdo->lastInsertId();
    }

    function update($table, $data, $where = null)
    {

        $changes = [];

        list($where, $bindings) = DB\Util::where($where);

        foreach ($data as $key => $value) {
            $changes[] = "$key = :update_$key";
            $bindings[":update_$key"] = $value;
        }

        if (empty($changes)) {
            throw new \Exception("Nothing to UPDATE");
        }

        $sql = sprintf("UPDATE %s SET %s WHERE %s",
            $table,
            implode(", ", $changes),
            $where);

        $this->query($sql);

        return $this->exec($bindings);
    }

    /**
     * Performs a DELETE query, returns number of affected rows
     *
     * @param $table table name
     * @param $where condition
     * @return int Number of affected rows
     * @throws \Exception
     */
    function destroy($table, $where)
    {

        list($where, $bindings) = DB\Util::where($where);

        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);

        $this->query($sql);

        return $this->exec($bindings);
    }

    /** DB Functions */

    /**
     * Returns a DB\Table helper for this table
     * @param $table String the table name
     * @return DB\Table
     */
    function table($table)
    {
        return new DB\Table($this, $table);
    }

    function escape($string)
    {
        return $this->pdo->quote($string);
    }
}