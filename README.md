DB  ![Build Status](https://travis-ci.org/objectiveweb/db.svg?branch=master)
==

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

    // select JOIN
    $rows = $db->select('table', [], ['join' => [
       'othertable o on o.some_id = table.id',  // full join in single line
       'table1' => 'table1.some_id = table.id', // inner join table1 table1 on table1.some_id = table.id
       '*table1' => 'table1.some_id = table.id' // left join table1 table1 on ...
    ]);

    // select JOIN with raw query
    $rows = $db->select('table', [], ['join' => 'othertable b on b.x = table.id']);

    // fetch row by row
    $query = $db->select('table');

    while($row = $query->fetch()) {
        // process $row
    }

    // delete (table, conditions)
    $db->delete('table', array('field' => 'value'));

    // transactions
    $db->transaction(function() use ($somevar) {
        $id = $db->insert(...);
        $db->update(...);

        if($condition) {
            throw new \Exception('Error - transaction rolled back');
        } else {
            return $id;
        }
    });

CRUD Operations
---------------

    use Objectiveweb\DB;

    $db = new DB(...);

    $table = $db->table('tablename', [
        'pk' => 'id',
        'join' => []
    ]);

    // Insert
    $id = $table->post(array('field' => 'value', ...);

    // Select all rows (returns DB\Collection)
    $table->index();

    // Get parameters
    $data = $table->index([ 
        'filter' => [ 'field' => 'value' ], 
        'sort' => ['id', 'asc'],
        'range' => [ 0, 4 ]
    ]);

    // Number of results
    count($data);

    // Total number of results (when using range)
    $data->total();

    foreach($data as $item) {
        $item['field'];
    }

    // Update (key, values)
    $affected_rows = $table->put(array('name' = 'new name'), array('name' => 'old name'));

    // Update by ID
    $affected_rows = $table->put(id, array('field' => 'new value'));


Extending DB\Table
------------------

    class MyTable extends Objectiveweb\DB\Table {
        var $table = 'table_name';
        var $params = [
            'pk => 'id',
            'join' => []
        ];
    }

    // then, instantiate it
    $table = $db->table('MyTable');

    $table->post(array('name' => 'new item'));
