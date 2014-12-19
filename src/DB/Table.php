<?php

namespace Objectiveweb\DB;

class Table {

  /** @var \Objectiveweb\DB */
  private $db;

  /** @var String */
  private $table;

  public function __construct($db, $table) {
    $this->db = $db;
    $this->table = $table;

    // TODO table metadata

  }

  /**
   * @param $key
   * @return mixed
   */
  public function get($key) {
    return $this->db->select($this->table, array(
      'where' => sprintf('id = %d', $key)
    ))->fetch();
  }

  public function post($data) {
    return $this->db->insert($this->table, $data);
  }

  public function put($key, $data) {
    return $this->db->update($this->table, $data, $key);
  }

  public function destroy($key) {
    return $this->db->destroy($this->table, $key);
  }
}