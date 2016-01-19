<?php

namespace Objectiveweb\DB;

use Objectiveweb\DB;

class Table
{

    /** @var \Objectiveweb\DB */
    protected $db;

    protected $pk = null;

    /** @var String */
    protected $table = null;

    /**
     * Table is a controller for a DB table
     * Usually instantiated via $db->table('tablename' [, 'id']);
     *
     * @param \Objectiveweb\DB $db DB instance
     * @param string $table table name
     * @param string $pk Optional Primary Key, defaults to 'id'
     */
    public function __construct(DB $db, $table = null, $pk = 'id')
    {
        $this->db = $db;

        if(!$this->table) {
            $this->table = $table;
        }

        if(!$this->pk) {
            $this->pk = $pk;
        }

        // TODO table metadata (lazy load)

    }

    /**
     * Wraps DB::select for this table
     * @param mixed $where conditions
     * @param array $params SELECT parameters
     * @return Query
     * @throws \Exception
     */
    public function select($where = null, $params = array()) {
        return $this->db->select($this->table, $where, $params);
    }

	/**
	 * get($query = array())
     *  Returns a list of rows matching $query.
     *  You can define
     *   $query[page] the page to retrieve (starting at 0)
     *   $query[size] the number of results per page
     *   $query[sort] the results sort order
     */
	public function index($key = array()) {
		$page = 0;
		$size = 20;
		$sort = '';

		if(is_array($key)) {

			if(isset($key['page'])) {
				$page = intval($key['page']);
				unset($key['page']);
			}

			if(isset($key['size'])) {
				$size = empty($key['size']) ? $size : intval($key['size']);
				unset($key['size']);
			}

			if(isset($key['sort'])) {
				$sort = $key['sort'];
				unset($key['sort']);
			}
		}

		// $params => page, size, sort
		$params = array(
			'fields' => 'SQL_CALC_FOUND_ROWS *',
			'sort' => $sort
		);

		if($size > 0) {
			$params['limit'] = $size;
			$params['offset'] = $page * $size;
		}

		$query = $this->select($key, $params);

		$rows_query = $this->db->query('SELECT FOUND_ROWS() as count');
		$rows_query->exec();

		$rows = $rows_query->fetch();

		if(!$rows) {
			throw new \Exception('Error while FOUND_ROWS()', 500);
		}

		$count = intval($rows['count']);

		return array(
			'_embedded' => array(
				$this->table => $query->all()
			),
			'page' => array(
				'size' => $size,
				'number' => $page,
				'totalElements' => $count,
				'totalPages' => ceil($count / $size)
			)
		);
	}
	
    /**
     * Retrieves records from table
     * 
	 * get()
	 * get($query = array())
	 *  @see index($query = array())
     * get($key, $params = array())
     *  Returns row with key $key, with optional select $params
     *
     * @param mixed $key
     * @param array $params
     * @return mixed
     */
    public function get($key = null, $params = array())
    {
		
		if(empty($key) || is_array($key)) {
			return $this->index($key);
		}
		
		// get single
		$key = sprintf('`%s` = %s', $this->pk, $this->db->escape($key));
		$query = $this->select($key, $params);

		if(!$rsrc = $query->fetch()) {
			throw new \Exception('Record not found', 404);
		}

		return $rsrc;
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

        return array('updated' => $this->db->update($this->table, $data, $key));
    }
	
    public function delete($key)
    {
        if(!is_array($key)) {
            $key = array( $this->pk => $key );
        }

        return $this->db->delete($this->table, $key);
    }
}