<?php

namespace Objectiveweb\DB;

class Table
{

    /** @var \Objectiveweb\DB */
    private $db;

    private $pk;

    /** @var String */
    private $table;

    /**
     * Table is a controller for a DB table
     * Usually instantiated via $db->table('tablename' [, 'id']);
     *
     * @param \Objectiveweb\DB $db DB instance
     * @param string $table table name
     * @param string $pk Optional Primary Key, defaults to 'id'
     */
    public function __construct($db, $table, $pk = 'id')
    {
        $this->db = $db;
        $this->table = $table;
        $this->pk = $pk;

        // TODO table metadata

    }

    /**
     * Retrieves records from table
     *
     * get($key, $params = array())
     *  Returns row with key $key, with optional select $params
     *
     * get($query = array(), $params = array())
     *  Returns a list of rows matching $query, with optional select $params
     *  If $query contains fields named _*, they are mapped to params
     *
     * @param mixed $key
     * @param array $params
     * @return mixed
     */
    public function get($key = null, $params = array())
    {
        if(!empty($key) && !is_array($key)) {
            // get single
            $key = sprintf('`%s` = %s', $this->pk, $this->db->escape($key));
            $query = $this->db->select($this->table, $key, $params);

            if(!$rsrc = $query->fetch()) {
                throw new \Exception('Record not found', 404);
            }

            return $rsrc;
        }
        else {

            if(is_array($key)) {

                // filter _* fields as params
                foreach($key as $field => $value) {
                    if($field[0] == '_') {
                        $params[substr($field, 1)] = $value;
                        unset($key[$field]);
                    }
                }

            }

            $query = $this->db->select($this->table, $key, $params);

            return $query->all();
        }
    }

    public function post($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function put($key, $data)
    {
        if(!is_array($key)) {
            $key = array( $this->pk => $key );
        }

        return $this->db->update($this->table, $data, $key);
    }

    public function destroy($key)
    {
        if(!is_array($key)) {
            $key = array( $this->pk => $key );
        }

        return $this->db->destroy($this->table, $key);
    }
}