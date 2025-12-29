<?php

namespace Objectiveweb\DB;

use Objectiveweb\DB;

/**
 * Class CrudTrait
 * @package Objectiveweb\DB
 *
 * This class includes basic functions for searching, creating, updating and deleting data
 *
 * To use it, your class shoud call $this->crudSetup($db, $table, $pk) on its constructor
 */
trait CrudTrait
{
    /** @var \Objectiveweb\DB */
    protected $db;

    /** @var String */
    protected $table = null;

    protected $params = null;

    protected function crudSetup(DB $db, $table, $params = [])
    {
        $this->db = $db;

        if (!$this->table) {
            $this->table = $table;
        }

        if (!$this->params) {
            $this->params = array_merge([
                'pk' => 'id',
                'join' => [],
                'fields' => ["$this->table.*"]
            ], $params);
        }


        $this->params['pk'] = implode('`.`', explode('.', $this->params['pk']));

    }

    public function index($params = [], $filter = [])
    {
        return $this->select($filter, $params);
    }

    private function parse_params($params)
    {
        // Other query parameters
        $queryparams = [];
        if (empty($params['fields'])) {
            $params['fields'] = $this->params['fields'];
        }

        if (!is_array($params['fields'])) {
            $params['fields'] = explode(',', $params['fields']);
        }

        $first_field = array_key_first($params['fields']);
        $params['fields'][$first_field] = 'SQL_CALC_FOUND_ROWS ' . $params['fields'][$first_field];

        $queryparams['fields'] = $params['fields'];

        if (!empty($params['sort'])) {
            if (!is_array($params['sort'])) {
                $sort = json_decode($params['sort']);
            } else {
                $sort = $params['sort'];
            }

            $queryparams['order'] = implode(" ", $sort);
        }

        if (!empty($params['range'])) {
            if (!is_array($params['range'])) {
                $params['range'] = json_decode($params['range']);
            }
            $queryparams['offset'] = $params['range'][0];
            $queryparams['limit'] = $params['range'][1] - $params['range'][0] + 1;
        } else {
            $queryparams['offset'] = 0;
        }

        $queryparams['join'] = isset($params['join']) ? $params['join'] : $this->params['join'];
        $queryparams['group'] = isset($params['group']) ? $params['group'] : $this->params['group'];

        return $queryparams;
    }
    /**
     * select(array $filter, array $params)
     *  Returns a list of rows matching $query.
     *
     * @param array $filter Default filter that is merged with params, overrides any user-provided values
     * @param array $params
     *  You can define
     *   $params[range] range of records to return
     *   $params[sort] the results sort order
     * @return Collection
     * @throws \Exception
     */

    public function select(array $filter = [], array $params = []): Collection
    {
        // Where clause
        if (!empty($params['filter'])) {
            if (!is_array($params['filter'])) {
                $where = array_merge(json_decode($params['filter'], true), $filter);
            } else {
                $where = array_merge($params['filter'], $filter);
            }
        } else {
            $where = $filter;
        }

        $queryparams = $this->parse_params($params);

        $query = $this->db->select($this->table, $where, $queryparams);

        $rows_query = $this->db->query('SELECT FOUND_ROWS() as count');
        $rows_query->exec();

        $rows = $rows_query->fetch();

        if (!$rows) {
            throw new \Exception('Error while FOUND_ROWS()', 500);
        }

        $data = $query->all();
        $rowscount = intval($rows['count']);

        return new Collection($data, $queryparams['offset'], $queryparams['offset'] + count($data) - 1, $rowscount);
    }

    /**
     * Retrieves records from table
     *
     * get()
     * get($query = array())
     * @param mixed $key
     * @param array $params
     * @return mixed
     * @see index($query = array())
     * get($key, $params = array())
     *  Returns row with key $key, with optional select $params
     *
     */
    public function get($key = null, $params = [])
    {
        if (empty($key) || is_array($key)) {
            return $this->select($key, $params);
        }

        $params = $this->parse_params($params);

        // get single
        $key = sprintf('`%s` = %s', $this->params['pk'], $this->db->escape($key));
        $query = $this->db->select($this->table, $key, $params);

        if (!$rsrc = $query->fetch()) {
            throw new \Exception('Record not found', 404);
        }

        return $rsrc;
    }

    public function insert(array $data): ?array
    {
        return $this->post($data);
    }

    public function post($data)
    {
        $id = $this->db->insert($this->table, $data);

        return $id ? [$this->params['pk'] => $id] : null;
    }

    public function put($key, $data)
    {
        if (!is_array($key)) {
            $key = array($this->params['pk'] => $key);
        }

        return array('updated' => $this->db->update($this->table, $data, $key));
    }

    public function delete($key)
    {
        if (!is_array($key)) {
            $key = array($this->params['pk'] => $key);
        }

        // TODO support cascade delete com joins?

        return $this->db->delete($this->table, $key);
    }

    public function findBy($key, $value)
    {
        return $this->select([
            'filter' => [
                $key => $value
            ]
        ]);
    }
}
