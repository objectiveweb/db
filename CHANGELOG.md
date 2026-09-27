# Changelog

All notable changes to this project will be documented in this file.

The project follows Semantic Versioning.

## 0.8.0

Major modernization release of Objectiveweb DB. This is a pre-1.0 release and contains breaking changes from 0.7.

### Added

- Doctrine DBAL-based database abstraction with SQLite, MySQL, and PostgreSQL support.
- Table API with CRUD operations, filtering, sorting, ranges, joins, grouping, aggregates, and row locking.
- Optional model mapping through the public `Model` extension API, with field filtering and validation rules.
- Table inheritance across base and child tables with transactional writes.
- Structured SQL expressions through the public `Expr` API.
- Transaction helpers and typed database exceptions.
- Connection health checking and reconnection support.
- Collection pagination metadata through `total()` and `contentRange()`.
- Stable `Query::exec()` semantics: DBAL `Result::rowCount()` for result-set queries and affected-row count for statement queries.
- Multi-database Docker test environment.
- GitHub Actions coverage for SQLite, MySQL, and PostgreSQL on every supported PHP version.

### Safety

- Table and field identifiers are validated before SQL generation.
- Raw string WHERE clauses are rejected.
- UPDATE and DELETE operations without conditions are rejected.
- Query values are passed through parameter bindings.

### Compatibility

- PHP 8.2, 8.3, and 8.4.
- Doctrine DBAL 4.x.
- SQLite.
- MySQL 8.4.
- PostgreSQL 16.

Doctrine DBAL 4.x compatibility is part of the Objectiveweb DB 0.8.x compatibility target.

The documented 0.8.x public API includes `Objectiveweb\\DB`, `Table`, `Collection`, `Query`, `Model`, and `Expr`, including the documented CRUD return contracts. Patch releases in the 0.8.x series should preserve these contracts.
