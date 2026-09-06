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
  return readFile(path.join('components', `${name}.js`), 'utf8');
}

test('DB exposes exactly six standalone Metaproject components', async () => {
  const files = (await readdir('components')).sort();
  assert.deepEqual(files, expected.map(name => `${name}.js`).sort());
  for (const name of expected) {
    const source = await component(name);
    assert.match(source, new RegExp(`customElements\\.define\\('${name}'`));
    for (const other of expected) {
      if (other === name) continue;
      assert.doesNotMatch(source, new RegExp(`<${other}\\b`));
    }
  }
});

test('database and table lists pass navigation parameters explicitly', async () => {
  const databases = await component('db-list');
  const tables = await component('db-table-list');
  assert.match(databases, /api\.listDatabases\(\)/);
  assert.match(databases, /navigate\('db-table-list', \{ dbName: database\.name \}\)/);
  assert.match(tables, /api\.listTables/);
  assert.match(tables, /navigate\('db-table-schema'/);
  assert.match(tables, /navigate\('db-table-rows'/);
  assert.match(tables, /primaryKey: table\.primaryKey/);
  assert.match(tables, /writable: Boolean\(table\.writable\)/);
});

test('schema and row list have distinct API responsibilities', async () => {
  const schema = await component('db-table-schema');
  const rows = await component('db-table-rows');
  assert.match(schema, /api\.getTableSchema/);
  assert.doesNotMatch(schema, /api\.listRows/);
  assert.match(rows, /api\.listRows/);
  assert.doesNotMatch(rows, /api\.getTableSchema/);
  assert.match(rows, /Object\.keys\(rows\[0\]\)/);
  assert.match(rows, /navigate\('db-row-details'/);
  assert.match(rows, /navigate\('db-row-editor'/);
});

test('row details loads only the row and renders arbitrary fields', async () => {
  const details = await component('db-row-details');
  assert.match(details, /api\.getRow/);
  assert.doesNotMatch(details, /api\.getTableSchema/);
  assert.match(details, /Object\.entries\(this\.row\)/);
});

test('row editor is empty without an id and dynamically edits writable schema fields', async () => {
  const editor = await component('db-row-editor');
  assert.match(editor, /this\.id = ''/);
  assert.match(editor, /No record loaded\./);
  assert.match(editor, /api\.getTableSchema/);
  assert.match(editor, /api\.getRow/);
  assert.match(editor, /api\.updateRow/);
  assert.match(editor, /filter\(column => column\.writable\)/);
  assert.match(editor, /metaproject-data-invalidate/);
});

test('all API calls normalize Mock and HTTP return types', async () => {
  for (const name of expected) {
    const source = await component(name);
    if (!/api\./.test(source)) continue;
    assert.match(source, /Promise\.(?:resolve|all)/, `${name} must normalize sync Mock and async HTTP results`);
  }
});
