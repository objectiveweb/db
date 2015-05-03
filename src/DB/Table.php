<?php

namespace Objectiveweb\DB;

class Table
{

    /** @var \Objectiveweb\DB */
    private $db;

    private $pk;

    /** @var String */
    private $table;

    public function __construct($db, $table, $pk = 'id')
    {
        $this->db = $db;
        $this->table = $table;
        $this->pk = $pk;

        // TODO table metadata

    }

    /**
     * @param $key
     * @param array $params [ fields => * ]
     * @return mixed
     */
    public function get($key = null, $params = array())
    {
        if($key && !is_array($key)) {
            // get single
            $key = sprintf('`%s` = %s', $this->pk, $this->db->escape($key));
            $query = $this->db->select($this->table, $key, $params);
            return $query->fetch();

        }
        else {
            // fetch
            $_params = array();

            foreach($params as $param => $value) {
                if($param[0] == '_') {
                    $_params[substr($param, 1)] = $value;
                }
                else {
                    $key[$param] = $value;
                }
            }

            $query = $this->db->select($this->table, $key, $_params);
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