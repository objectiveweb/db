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
        $this->pk = 'id';

        // TODO table metadata

    }

    /**
     * @param $key
     * @param array $params [ fields => * ]
     * @return mixed
     */
    public function get($key, $params = array())
    {

        if (!is_array($key)) {
            $key = sprintf('`%s` = %s', $pk, $this->db->escape($key));
        }

        $params['where'] = $key;

        return $this->db->select($this->table, $params)->fetch();
    }

    public function post($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function put($key, $data)
    {
        return $this->db->update($this->table, $data, $key);
    }

    public function destroy($key)
    {
        return $this->db->destroy($this->table, $key);
    }
}