<?php

namespace Objectiveweb\DB;

use Objectiveweb\DB;

class Table
{
    use CrudTrait;

    /**
     * Table is a controller for a DB table
     * Usually instantiated via $db->table('tablename' [, [ 'pk' => 'id'] ]);
     *
     * @param \Objectiveweb\DB $db DB instance
     * @param string $table table name
     * @param string $params Optional defaults to [ 'pk' => 'id', 'join' => [] ]
     */
    public function __construct(DB $db, $table = null, $params = [])
    {
        $this->crudSetup($db, $table, $params);
    }
}
