<!-- metaproject:start -->
## Metaproject

This project uses Metaproject for browser UI and API modeling.

When analyzing an existing application or adding an API-backed feature, first read:

`node_modules/metaproject/skills/application-analysis/SKILL.md`

That skill updates the canonical `openapi.yaml` model and then hands component creation to:

`node_modules/metaproject/skills/component-creator/SKILL.md`

For low-level component syntax and runtime rules, also consult:

`node_modules/metaproject/skills/component-authoring/SKILL.md`

Canonical Metaproject files are `metaproject.yaml`, `openapi.yaml`, and `components/`. Canvas layouts are stored in the `canvas:` list inside `metaproject.yaml`. Generated browser assets under `public/components/` must not be edited manually.
<!-- metaproject:end -->

## Objectiveweb DB Rules
- Prefer the service layer for database access; controllers orchestrate only.
- `DB::select()` and `Table::select()` filters must be arrays; raw `WHERE` strings are disabled.
- Filter behavior:
  - `['field' => '%abc%']` -> `LIKE`
  - `['field' => null]` -> `IS NULL`
  - `['!field' => value]` -> `<>` (or `NOT LIKE` with `%`)
  - `['field' => [1,2,3]]` -> `IN (...)`
- `update()` and `delete()` require a `WHERE` condition; unsafe calls throw errors.
- This codebase uses the legacy join-map form, for example `['left:table' => 'table.id = base.table_id']`.

## Service Query Patterns (`DB::table` / `Table`)
- In services, prefer table wrappers: `$table = $this->db->table('your_table', [...params...]);`.
- Define joins, fields, and grouping once in table parameters, then reuse them with `select()` and `get()`.
- Use `select($filter, $params)` for lists and `get($id)` or `get($filter)` for one record or filtered collection.
- Use `insert($data)`, `update($idOrWhere, $data)`, and `delete($idOrWhere)` for writes.
- Use `Table::select()` parameters consistently: `filter` for extra conditions, `sort` for ordering (string or list), and `range` for pagination (`[start, end]`).
- Keep table field aliases explicit (for example, `'venue.name' => 'venue.name'`) so templates and controllers receive stable keys.
- Keep business validation in services before `insert()` or `update()`.
- For multi-step writes, use `$this->db->transaction(function (DB $db) { ... });`.

## Database Migrations
- When changes concern the same domain as an uncommitted migration, prefer rolling it back and consolidating the changes in that migration file.
- Before rolling back migrations, run `make phinx status` and roll back only as far as the migration being changed.

## Related Objectiveweb package instructions

For work that crosses package boundaries, read the relevant package instructions first (paths are relative to the host application root):

- `vendor/objectiveweb/router/AGENTS.md`
- `vendor/objectiveweb/db/AGENTS.md`
- `vendor/objectiveweb/auth/AGENTS.md`
