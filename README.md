Bravado DB
==========

Proof-of-concept Database abstraction layer and management interface

Getting Started
---------------

  * Visit /login.php fill database details and username/login
  * Now visit /api.php
    * GET api.php - Lists databases
    * GET api.php/database - Lists "database" tables
    * GET api.php/database/table - Lists all records from "table" on database "database"
    * GET api.php/database/table/id - Retrieves a particular record from the database
  * login.php/logout logs you out

TODO
----

  * Review the API - is it ok/sane ?
  * Code the DB Class (DB/Table, DB/Query ?)
  * Further actions
    * POST / - Create database
    * PUT /database - Alter database
    * POST /database - Create table
    * PUT /database/table - Alter table
    * POST /database/table - Insert into
    * PUT /database/table/id - Update record with id = id
