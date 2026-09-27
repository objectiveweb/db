# Changelog

All notable changes to this project will be documented in this file.

The project follows Semantic Versioning from version 1.0.0 onward.

## 1.0.0

First stable release of the current Objectiveweb DB API.

### Added

- Doctrine DBAL-based database abstraction with SQLite, MySQL, and PostgreSQL support.
- Table API with CRUD operations, filtering, sorting, ranges, joins, grouping, aggregates, and row locking.
- Optional model mapping with field filtering and validation rules.
- Table inheritance across base and child tables with transactional writes.
- Structured SQL expressions through `Expr`.
- Transaction helpers and typed database exceptions.
- Connection health checking and reconnection support.
- Collection pagination metadata through `total()` and `contentRange()`.
- Multi-database Docker test environment.
- GitHub Actions coverage for SQLite, MySQL, and PostgreSQL on every supported PHP version.

### Safety

- Table and field identifiers are validated before SQL generation.
- Raw string WHERE clauses are rejected.
- UPDATE and DELETE operations without conditions are rejected.
- Query values are passed through parameter bindings.

### Compatibility

- PHP 8.2, 8.3, and 8.4.
- Doctrine DBAL 3.10 and Doctrine DBAL 4.x.
- SQLite.
- MySQL 8.4.
- PostgreSQL 16.

Doctrine DBAL 3.10 and 4.x compatibility is part of the Objectiveweb DB 1.x compatibility guarantee.
