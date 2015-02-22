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
        if ($key && !is_array($key)) {
            $key = sprintf('`%s` = %s', $this->pk, $this->db->escape($key));
            $this->db->select($this->table, $key, $params);
            return $this->db->fetch();
        }
        else {
            $this->db->select($this->table, $key, $params);
            return $this->db->all();
        }
    }

    public function post($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function put($key, $data)
    {
        if(!is_array($key)) {
            $key = [ $this->pk => $key];
        }

        return $this->db->update($this->table, $data, $key);
    }

    public function destroy($key)
    {
        if(!is_array($key)) {
            $key = [ $this->pk => $key];
        }

        return $this->db->destroy($this->table, $key);
    }
}