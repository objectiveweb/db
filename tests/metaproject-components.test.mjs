import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

const expected = [
  'db-list',
  'db-table-list',
  'db-table-schema',
  'db-table-rows',
  'db-row-editor'
];

async function component(name) {
  return readFile(path.join('components', name, 'component.html'), 'utf8');
}

async function helperModule(name) {
  const source = await readFile(path.join('components', name, 'component.js'), 'utf8');
  return import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
}

test('DB exposes exactly five reusable HTML-first components', async () => {
  const entries = await readdir('components', { withFileTypes:true });
  const directories = entries.filter(entry => entry.isDirectory()).map(entry => entry.name).sort();
  assert.deepEqual(directories, [...expected].sort());
  assert.equal(entries.some(entry => entry.isFile() && /\.(?:js|ts)$/.test(entry.name)), false);
});

test('DB components use declarative data-source requests and never call api directly', async () => {
  for (const name of expected) {
    const source = await component(name);
    for (const other of expected) assert.doesNotMatch(source, new RegExp(`<${other}\\b`), `${name} must not embed <${other}>`);
    assert.match(source, /<data-source\b/i, `${name} must declare its own query`);
    assert.doesNotMatch(source, /\bapi\./, `${name} must not call api.* directly`);
    assert.doesNotMatch(source, /Promise\.(?:resolve|all)/, `${name} must not manage request promises`);
  }
});

test('database list is listDatabases + generated table + Open navigation', async () => {
  const source = await component('db-list');
  assert.match(source, /<data-source request="listDatabases">/);
  assert.match(source, /<data-table/);
  assert.match(source, /name: 'open-database'/);
  assert.match(source, /navigate\('db-table-list', \{ dbName: event\.detail\.resource\.name \}\)/);
});

test('table Open updates both schema and rows panels with the same selected table', async () => {
  const source = await component('db-table-list');
  assert.match(source, /request="listTables"/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /name: 'open-table'/);
  assert.match(source, /navigate\('db-table-schema', \{ dbName, tableName \}\)/);
  assert.match(source, /navigate\('db-table-rows'/);
  assert.match(source, /primaryKey: table\.primaryKey \|\| ''/);
  assert.match(source, /writable: Boolean\(table\.writable\)/);
});

test('schema panel selects columns and lets data-table infer the table', async () => {
  const source = await component('db-table-schema');
  assert.match(source, /request="getTableSchema"/);
  assert.match(source, /select="columns"/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /table-name=\$\{tableName\}/);
  assert.match(source, /<data-table><\/data-table>/);
  assert.doesNotMatch(source, /<template\b/);
  assert.doesNotMatch(source, /navigate\(/);
});

test('rows panel selects page.data and generated table edits writable rows', async () => {
  const source = await component('db-table-rows');
  const helper = await helperModule('db-table-rows');
  assert.match(source, /request="listRows"/);
  assert.match(source, /select="data"/);
  assert.match(source, /\.actions=\$\{writable/);
  assert.match(source, /resolveRowId\(event\.detail\.resource, primaryKey\)/);
  assert.match(source, /navigate\('db-row-editor', \{ dbName, tableName, id \}\)/);
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'code'), 'A');
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'missing'), '1');
  assert.equal(helper.resolveRowId({}, 'id'), '');
});

test('row editor is one row query plus generated data-form plus update mutation', async () => {
  const source = await component('db-row-editor');
  assert.match(source, /name="id" type="string" required example="1"/);
  assert.match(source, /request="getRow"/);
  assert.match(source, /save="updateRow"/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /table-name=\$\{tableName\}/);
  assert.match(source, /id=\$\{id\}/);
  assert.match(source, /<data-form><\/data-form>/);
  assert.doesNotMatch(source, /getTableSchema/);
  assert.doesNotMatch(source, /form-input|data-table|data-action/);
  assert.doesNotMatch(source, /navigate\(/);
});
