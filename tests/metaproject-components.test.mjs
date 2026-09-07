import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

const expected = [
  'db-list',
  'db-table-list',
  'db-table-schema',
  'db-table-rows',
  'db-row-details',
  'db-row-editor'
];

async function component(name) {
  return readFile(path.join('components', name, 'component.html'), 'utf8');
}

test('DB exposes exactly six HTML-first components', async () => {
  const entries = await readdir('components', { withFileTypes:true });
  const directories = entries.filter(entry => entry.isDirectory()).map(entry => entry.name).sort();
  assert.deepEqual(directories, [...expected].sort());
  assert.equal(entries.some(entry => entry.isFile() && /\.(?:js|ts)$/.test(entry.name)), false);
});

test('DB components never nest another DB component', async () => {
  for (const name of expected) {
    const source = await component(name);
    for (const other of expected) {
      assert.doesNotMatch(source, new RegExp(`<${other}\\b`), `${name} must not embed <${other}>`);
    }
  }
});

test('database and table lists pass navigation parameters explicitly', async () => {
  const databases = await component('db-list');
  const tables = await component('db-table-list');
  assert.match(databases, /api\.listDatabases\(\)/);
  assert.match(databases, /navigate\('db-table-list', \{ dbName: event\.detail\.resource\.name \}\)/);
  assert.match(tables, /api\.listTables/);
  assert.match(tables, /navigate\('db-table-schema'/);
  assert.match(tables, /navigate\('db-table-rows'/);
  assert.match(tables, /primaryKey: event\.detail\.resource\.primaryKey/);
  assert.match(tables, /writable: Boolean\(event\.detail\.resource\.writable\)/);
});

test('schema and rows components have distinct API responsibilities', async () => {
  const schema = await component('db-table-schema');
  const rows = await component('db-table-rows');
  assert.match(schema, /api\.getTableSchema/);
  assert.doesNotMatch(schema, /api\.listRows/);
  assert.match(rows, /api\.listRows/);
  assert.doesNotMatch(rows, /api\.getTableSchema/);
  assert.match(rows, /\.actions=\$\{/);
  assert.match(rows, /navigate\('db-row-details'/);
  assert.match(rows, /navigate\('db-row-editor'/);
});

test('row actions resolve the selected identifier without ever navigating to undefined', async () => {
  const rows = await component('db-table-rows');
  const helper = await import(new URL('../components/db-table-rows/component.js', import.meta.url));
  assert.match(rows, /resolveRowId\(event\.detail\.resource, primaryKey\)/);
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'code'), 'A');
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'missing'), '1');
  assert.equal(helper.resolveRowId({ id:1 }, 'undefined'), '1');
  assert.equal(helper.resolveRowId({}, 'id'), '');
});

test('row details loads one row and renders field-value records', async () => {
  const details = await component('db-row-details');
  const helper = await readFile('components/db-row-details/component.js', 'utf8');
  assert.match(details, /api\.getRow/);
  assert.doesNotMatch(details, /api\.getTableSchema/);
  assert.match(details, /rowFields\(row\)/);
  assert.match(helper, /Object\.entries\(row \|\| \{\}\)/);
});

test('row editor stays empty until navigation supplies an id and saves a dynamic form', async () => {
  const editor = await component('db-row-editor');
  const helper = await readFile('components/db-row-editor/component.js', 'utf8');
  assert.match(editor, /name="id" type="string" default=""/);
  assert.doesNotMatch(editor, /name="id"[^>]*example=/);
  assert.match(editor, /No record loaded\./);
  assert.match(editor, /api\.getTableSchema/);
  assert.match(editor, /api\.getRow/);
  assert.match(editor, /api\.updateRow/);
  assert.match(editor, /form-input field="value"/);
  assert.match(helper, /filter\(column => column\.writable\)/);
  assert.match(helper, /metaproject-data-invalidate/);
});

test('Mock and HTTP API results are normalized through Promise.resolve', async () => {
  for (const name of expected) {
    const source = await component(name);
    if (!/api\./.test(source)) continue;
    assert.match(source, /Promise\.(?:resolve|all)/, `${name} must normalize synchronous Mock and asynchronous HTTP responses`);
  }
});

test('native data-row templates remain static', async () => {
  for (const name of expected) {
    const source = await component(name);
    for (const match of source.matchAll(/<template\b[^>]*>([\s\S]*?)<\/template\s*>/gi)) {
      assert.equal(match[1].includes('${'), false, `${name} contains a Lit expression inside native template`);
    }
  }
});
