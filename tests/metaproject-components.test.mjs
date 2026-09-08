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

test('DB components never nest another DB component or own a data-source provider', async () => {
  for (const name of expected) {
    const source = await component(name);
    for (const other of expected) assert.doesNotMatch(source, new RegExp(`<${other}\\b`), `${name} must not embed <${other}>`);
    assert.doesNotMatch(source, /<data-source\b/i, `${name} must inherit the contextual/default DataSource instead of declaring one`);
  }
});

test('database selection opens the table list', async () => {
  const databases = await component('db-list');
  assert.match(databases, /api\.listDatabases\(\)/);
  assert.match(databases, /navigate\('db-table-list', \{ dbName: event\.detail\.resource\.name \}\)/);
});

test('table Open updates both schema and rows panels with the same selected table', async () => {
  const tables = await component('db-table-list');
  assert.match(tables, /data-action="open-table"/);
  assert.match(tables, /navigate\('db-table-schema', \{ dbName, tableName \}\)/);
  assert.match(tables, /navigate\('db-table-rows'/);
  assert.match(tables, /primaryKey: table\.primaryKey \|\| ''/);
  assert.match(tables, /writable: Boolean\(table\.writable\)/);
  assert.doesNotMatch(tables, /open-schema|open-rows/);
});

test('schema and rows components have distinct API responsibilities', async () => {
  const schema = await component('db-table-schema');
  const rows = await component('db-table-rows');
  assert.match(schema, /api\.getTableSchema/);
  assert.doesNotMatch(schema, /api\.listRows/);
  assert.doesNotMatch(schema, /navigate\(/);
  assert.match(rows, /api\.listRows/);
  assert.doesNotMatch(rows, /api\.getTableSchema/);
  assert.match(rows, /\.actions=\$\{writable/);
  assert.match(rows, /navigate\('db-row-editor', \{ dbName, tableName, id \}\)/);
  assert.doesNotMatch(rows, /db-row-details|view-row/);
});

test('row edit action resolves the selected identifier without ever navigating to undefined', async () => {
  const rows = await component('db-table-rows');
  const helper = await helperModule('db-table-rows');
  assert.match(rows, /resolveRowId\(event\.detail\.resource, primaryKey\)/);
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'code'), 'A');
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'missing'), '1');
  assert.equal(helper.resolveRowId({ id:1 }, 'undefined'), '1');
  assert.equal(helper.resolveRowId({}, 'id'), '');
});

test('row editor generates a writable-field form for one row and saves through updateRow', async () => {
  const editor = await component('db-row-editor');
  const helper = await readFile('components/db-row-editor/component.js', 'utf8');
  const module = await helperModule('db-row-editor');
  assert.equal(module.normalizeRowId(undefined), '');
  assert.equal(module.normalizeRowId('undefined'), '');
  assert.equal(module.normalizeRowId('null'), '');
  assert.equal(module.normalizeRowId(' 2 '), '2');
  assert.match(editor, /name="id" type="string" default=""/);
  assert.match(editor, /api\.getTableSchema/);
  assert.match(editor, /api\.getRow/);
  assert.match(editor, /api\.updateRow/);
  assert.match(editor, /form-input field="value"/);
  assert.match(editor, /data-action="save-row"/);
  assert.doesNotMatch(editor, /navigate\(/);
  assert.match(helper, /filter\(column => column\.writable\)/);
  assert.doesNotMatch(helper, /metaproject-data-invalidate/);
});

test('Mock and HTTP API results are normalized where a read can be sync or async', async () => {
  for (const name of expected) {
    const source = await component(name);
    if (!/api\.(?:list|get)/.test(source)) continue;
    assert.match(source, /Promise\.(?:resolve|all)/, `${name} must normalize synchronous Mock and asynchronous HTTP reads`);
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
