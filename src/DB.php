<?php
namespace Objectiveweb;

use Objectiveweb\DB\Query;
use PDO;


class DB
{
    /** @var \PDO  */
    public $pdo;

    public $error = null;

    /** @var  $stmt */
    private $stmt;

    function __construct($uri, $username, $password = '', $options = array())
    {

        $defaults = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        );

        $this->pdo = new PDO($uri, $username, $password, array_merge($defaults, $options));
    }

    function query($query) {
        if (func_num_args() > 1) {
            $query = call_user_func_array('sprintf', func_get_args());
        }

        $stmt = $this->pdo->prepare($query);

        return new Query($stmt);
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

    /**
     * Returns TRUE on success or FALSE on failure.
     */
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

        $defaults = array(
            'fields' => '*',
            'group' => NULL,
            'order' => NULL,
            'limit' => NULL,
            'offset' => 0
        );

        $params = array_merge($defaults, $params);

        if(is_array($table)) {
            throw new \Exception('not implemented');
        }
        else {
            $join = '';
        }

        if(is_array($params['fields'])) {
            throw new \Exception('not implemented');
        }
        else {
            $fields = $params['fields'];
        }

        list($where, $bindings) = DB\Util::where($where);

        $sql = sprintf("SELECT %s FROM `%s` %s %s",
            $fields,
            $table,
            $join,
            !empty($where) ? 'WHERE '.$where : '');

        if($params['group']) {
            if(is_array($params['group'])) {
                throw new \Exception('not implemented');
            }
            else {
                $sql .= sprintf(' GROUP BY %s', $params['group']);
            }
        }

        if($params['order']) {
            if(is_array($params['order'])) {
                throw new \Exception('not implemented');
            }
            else {
                $sql .= sprintf(' ORDER BY %s', $params['order']);
            }
        }

        if($params['limit']) {
            $sql .= sprintf(' LIMIT %d,%d', $params['offset'], $params['limit']);
        }


        $query = $this->query($sql);

        $query->exec($bindings);

        return $query;
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

        $query = $this->query($sql);
        foreach ($fields as $field) {
            $query->bind($field, $data[$field]);
        }

        $rows = $query->exec();

        return ($rows === 0) ? NULL : $this->pdo->lastInsertId();
    }

    function update($table, $data, $where = null)
    {

        $changes = array();

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

        $query = $this->query($sql);

        return $query->exec($bindings);
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

        $query = $this->query($sql);

        return $query->exec($bindings);
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