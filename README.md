Objectiveweb DB
==========

Database abstraction layer

Getting Started
---------------

    use Objectiveweb\DB;

    $db = new DB('pdo uri', 'username', 'password');

    // general queries
    $db->query('create table ...')->exec();

    // insert
    $insert_id = $db->insert('table', array('field' => 'value', 'otherfield' => 'value'));

    // update (table, values, conditions)
    $affected_rows = $db->update('table', array('field' => 'newvalue', ...), array('field' => 'value'));

    // select all rows
    // returns array ( row1, row2, ...)
    $rows = $db->select('table')->all();

    // map results by field
    // returns associative array { 'row1_field_value' => row1, 'row2_field_value' => row2, ...)
    $rows = $db->select('table')->map('field');

    // select IN
    $rows = $db->select('table', array('ID' => array( 1, 2, 3))->all();

    // fetch row by row
    $query = $db->select('table');

    while($row = $query->fetch()) {
        // process $row
    }

    // delete (table, conditions)
    $db->destroy('table', array('field' => 'value'));

Table Controller
----------------

    use Objectiveweb\DB;

    $db = new DB(...);

    $table = $db->table('tablename');

    // Insert
    $id = $table->post(array('field' => 'value', ...);

    // Select all rows
    $rows = $table->get();

    // Update (key, values)
    $affected_rows = $table->put(array('name' = 'new name'), array('name' => 'old name'));

    // Update by ID
    $affected_rows = $table->put(id, array('field' => 'new value'));


Extending DB\Table
------------------

    class MyTable extends Objectiveweb\DB\Table {
        var $table = 'table_name';
        var $pk = 'id';
    }

    // then, instantiate it
    $table = $db->table('MyTable');

    $table->post(array('name' => 'new item'));

TODO
----

  * Management Interface: Review the API - is it ok/sane ?
    * Parameters should follow the sql syntax (json-encoded?)
  * Write tests!
  * Code the DB Class (DB/Table, DB/Query ?)
  * Further actions
    * POST / - Create database
    * PUT /database - Alter database
    * POST /database - Create table
    * PUT /database/table - Alter table
    * POST /database/table - Insert into
    * PUT /database/table/id - Update record with id = id
	* ...