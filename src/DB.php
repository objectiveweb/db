<?php

namespace Objectiveweb;

use Objectiveweb\DB\Query;
use PDO;


class DB
{
    /** @var \PDO */
    public $pdo;

    private $debug = false;

    public $error = null;

    private $prefix = null;

    function __construct(\PDO $pdo, $prefix = null)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    /**
     * Creates a new DB instance
     *
     * @param string $dsn driver:dbname=name;host=127.0.0.1;charset=utf8
     *
     *  The Data Source Name, or DSN, contains the information required to connect to the database.
     *
     *    In general, a DSN consists of the PDO driver name, followed by a colon, followed by the PDO driver-specific connection syntax. Further information is available from the PDO driver-specific documentation.
     *
     *    The dsn parameter supports three different methods of specifying the arguments required to create a database connection:
     *
     *    Driver invocation
     *    dsn contains the full DSN.
     *
     *    URI invocation
     *    dsn consists of uri: followed by a URI that defines the location of a file containing the DSN string. The URI can specify a local file or a remote URL.
     *
     *    uri:file:///path/to/dsnfile
     *
     *    Aliasing
     *    dsn consists of a name name that maps to pdo.dsn.name in php.ini defining the DSN string.
     *
     * @param string $username
     *  The user name for the DSN string. This parameter is optional for some PDO drivers.
     *
     * @param string $password
     *  The password for the DSN string. This parameter is optional for some PDO drivers.
     *
     * @param array $options
     *  PDO key=>value array of driver-specific connection options.
     *
     * @return \Objectiveweb\DB
     */
    public static function connect($dsn, $username = null, $password = '', $options = array())
    {

        // parse dsn if necessary
        if (is_array($dsn)) {
            $username = $dsn['user'];
            $password = @$dsn['pass'];
            $dsn = sprintf("%s:dbname=%s;host=%s;charset=utf8",
                $dsn['scheme'],
                isset($dsn['dbname']) ? $dsn['dbname'] : substr($dsn['path'], 1),
                $dsn['host']);
        }

        $prefix = @$options['prefix'];
        unset($options['prefix']);

        return new DB(new PDO($dsn, $username, $password, $options), $prefix);

    }

    function query($sql)
    {
        if (func_num_args() > 1) {
            $sql = call_user_func_array('sprintf', func_get_args());
        }

        $stmt = $this->pdo->prepare($sql);

        $query = new Query($stmt);

        if ($this->debug) {
            $query->sql = $sql;
        }

        return $query;
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
            $ret = call_user_func($callable, $this);

            if(!$this->commit()) {
                throw new \Exception('Cannot commit transaction', 500);
            }

            return $ret;
        } catch (\Exception $ex) {
            $this->rollBack();
            throw $ex;
        }
    }

    /* sql helpers  ------------------------------------------------ */

    /**
     * Performs a SELECT Query
     * @param $table
     * @param $where array [ field => value ] or string
     * @param array $params [ key => value ]
     *  fields => comma-separated string or array.
     *   Non-numeric keys are used as field names, for example
     *   $fields = array( 'id', 'name', 'total' => 'COUNT(*)' );
     *  group => null
     *  order => null
     *  limit => null
     *  offset => 0
     *  join => array(
     *    'table' => 'table.id = other.id', // table -> condition syntax
     *    'othertable t on t.id = table.id' // raw string syntax
     *  )
     *
     * @return \Objectiveweb\DB\Query
     * @throws \Exception
     */
    function select($table, $where = null, $params = array())
    {

        $defaults = array(
            'fields' => '*',
            'group' => NULL,
            'order' => NULL,
            'limit' => NULL,
            'offset' => 0,
            'join' => ''
        );

        $params = array_merge($defaults, $params);

        /**
         * JOIN
         */
        if (is_array($params['join'])) {
            $join = '';
            foreach ($params['join'] as $k => $v) {
                if (is_numeric($k)) {
                    $join .= " $v";
                } else {
                    if ($k[0] == '*') {
                        $join .= ' left';
                        $k = ltrim($k, '*');
                    } else {
                        $join .= ' inner';
                    }
                    $join .= " join {$this->prefix}{$k} {$k} on {$v}";
                }
            }
        } else {
            $join = $params['join'];
        }

        /**
         * FIELDS
         */
        if (!is_array($params['fields'])) {
            $params['fields'] = explode(",", $params['fields']);
        }

        $fields = array();

        foreach ($params['fields'] as $k => $v) {

            // Allow * and functions
            if (preg_match('/(\*|[A-Z]+\([^\)]+\)|[a-z]+\([^\)]+\)|SQL_CALC_FOUND_ROWS.*)/', $v)) {
                $r = str_replace('`', '``', $v);
            } else {
                $r = "`" . implode('`.`', explode(".", str_replace('`', '``', $v))) . "`";
            }

            if (!is_numeric($k)) {
                $r .= sprintf(" as `%s`", str_replace('`', '``', $k));
            }

            //$fields[] = $r;
            $params['fields'][$k] = $r;
        }

        list($where, $bindings) = $this->_where($where);

        $sql = sprintf(/** @lang text */
            "SELECT %s FROM `%s` %s %s %s",
            implode(", ", $params['fields']),
            $this->prefix . $table,
            $table,
            $join,
            !empty($where) ? 'WHERE ' . $where : '');

        if ($params['group']) {
            if (is_array($params['group'])) {
                throw new \Exception('not implemented');
            } else {
                $sql .= sprintf(' GROUP BY %s', $params['group']);
            }
        }

        if (!empty($params['order'])) {
            if (is_array($params['order'])) {
                $params['order'] = implode(' ', $params['order']);
            }
            $sql .= sprintf(' ORDER BY %s', $params['order']);
        }

        if ($params['limit']) {
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

        $sql = "INSERT INTO " . $this->prefix . $table . " (" . implode(", ", $fields) . ") VALUES (:" . implode(", :", $fields) . ");";

        $query = $this->query($sql);
        foreach ($fields as $field) {
            $query->bind($field, $data[$field]);
        }

        $rows = $query->exec();

        return ($rows === 0) ? NULL : $this->pdo->lastInsertId();
    }


    /**
     * UPDATE
     *
     * @param String $table Table name
     * @param array $data Data to update
     * @param mixed $where conditions
     * @return int number of updated rows
     * @throws \Exception
     */
    function update($table, $data, $where = null)
    {

        $changes = array();

        list($where, $bindings) = $this->_where($where);

        foreach ($data as $key => $value) {
            $changes[] = "$key = :update_$key";
            $bindings[":update_$key"] = $value;
        }

        if (empty($changes)) {
            throw new \Exception("Nothing to UPDATE");
        }

        $sql = sprintf(/** @lang text */
            "UPDATE `%s` SET %s WHERE %s",
            $this->prefix . $table,
            implode(", ", $changes),
            $where);

        $query = $this->query($sql);

        return $query->exec($bindings);
    }

    /**
     * Performs a DELETE query, returns number of affected rows
     *
     * @param string $table table name
     * @param mixed $where condition
     * @return int Number of affected rows
     * @throws \Exception
     */
    function delete($table, $where)
    {

        list($where, $bindings) = $this->_where($where);

        $sql = sprintf(/** @lang text */
            "DELETE FROM `%s` WHERE %s", $this->prefix . $table, $where);

        $query = $this->query($sql);

        return $query->exec($bindings);
    }

    private function _where($args = null, $glue = "AND")
    {

        $bindings = null;

        if ($args && is_array($args)) {
            $cond = array();
            $bindings = array();
            $me = $this;

            // TODO suportar _and, _or
            foreach ($args as $key => $value) {

                // TODO if is_numeric($key)
                $table = str_replace('`', '``', $key);
                $table = implode('`.`', explode(".", $table));
                $key = crc32($key);

                if (is_array($value)) {
                    // TODO quote array values
                    $cond[] = sprintf("`%s` IN (%s)", $table, implode(",", array_map(array($this, 'escape'), $value)));
                } else {
                    $cond[] = sprintf("`%s` %s :where_%s",
                        $table,
                        is_null($value) ? 'is' : (strpos($value, '%') !== FALSE ? 'LIKE' : '='),
                        $key);
                    $bindings[":where_$key"] = $value;
                }
            }

            $args = implode(" $glue ", $cond);
        }

        return array($args, $bindings);
    }

    /** DB Functions */

    /**
     * Ativa debugging no db (grava queries, etc)
     * @param bool|true $status
     */
    function debug($status = array())
    {
        $this->debug = $status;
    }

    /**
     * Returns a DB\Table helper for this table
     * @param $table String the table name
     * @param array $params Optional Primary Key, defaults to 'id'
     * @return DB\Table
     */
    function table($table, array $params = ['pk' => 'id'])
    {
        if (class_exists($table) && is_subclass_of($table, 'Objectiveweb\DB\Table')) {

            return new $table($this);
        } else {
            return new DB\Table($this, $table, $params);
        }
    }

    function escape($string)
    {
        return $this->pdo->quote($string);
    }

    public static function array_cleanup(array $array, array $valid_keys = [], $defaults = [])
    {
        if (!is_array($valid_keys)) {
            $valid_keys = array($valid_keys);
        }
        $clean_array = array_intersect_key($array, array_flip($valid_keys));
        return array_merge($defaults, $clean_array);
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }
}
