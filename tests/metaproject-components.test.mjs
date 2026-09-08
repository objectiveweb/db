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

test('DB components use declarative data primitives and keep context in title metadata', async () => {
  for (const name of expected) {
    const source = await component(name);
    for (const other of expected) assert.doesNotMatch(source, new RegExp(`<${other}\\b`), `${name} must not embed <${other}>`);
    assert.match(source, /<data-source\b/i, `${name} must declare its own query`);
    assert.match(source, /<title>[^<]+<\/title>/, `${name} must declare title metadata`);
    assert.doesNotMatch(source, /<header\b|component-heading/, `${name} must not render duplicate chrome headings`);
    assert.doesNotMatch(source, /\bapi\./, `${name} must not call api.* directly`);
    assert.doesNotMatch(source, /Promise\.(?:resolve|all)/, `${name} must not manage request promises`);
    assert.doesNotMatch(source, /\bsave=/, `${name} must not map mutations through data-source save=`);
    assert.doesNotMatch(source, /\.actions\s*=/, `${name} must author table actions as HTML`);
  }
});

test('database list is listDatabases + generated table + authored Open action', async () => {
  const source = await component('db-list');
  assert.match(source, /<data-source request="listDatabases">/);
  assert.match(source, /<data-table/);
  assert.match(source, /<button data-action="open-database">Open<\/button>/);
  assert.match(source, /navigate\('db-table-list', \{ dbName: event\.detail\.resource\.name \}\)/);
});

test('table Open updates both schema and rows panels with the same selected table', async () => {
  const source = await component('db-table-list');
  assert.match(source, /<title>Tables — \$\{dbName\}<\/title>/);
  assert.match(source, /request="listTables"/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /<button data-action="open-table">Open<\/button>/);
  assert.match(source, /navigate\('db-table-schema', \{ dbName, tableName \}\)/);
  assert.match(source, /navigate\('db-table-rows'/);
  assert.match(source, /primaryKey: table\.primaryKey \|\| ''/);
  assert.match(source, /writable: Boolean\(table\.writable\)/);
});

test('schema panel selects columns and lets data-table infer the table', async () => {
  const source = await component('db-table-schema');
  assert.match(source, /<title>Schema — \$\{dbName\}\.\$\{tableName\}<\/title>/);
  assert.match(source, /request="getTableSchema"/);
  assert.match(source, /select="columns"/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /table-name=\$\{tableName\}/);
  assert.match(source, /<data-table><\/data-table>/);
  assert.doesNotMatch(source, /<template\b/);
  assert.doesNotMatch(source, /navigate\(/);
});

test('rows panel selects page.data and uses authored Edit action for writable rows', async () => {
  const source = await component('db-table-rows');
  const helper = await helperModule('db-table-rows');
  assert.match(source, /<title>Rows — \$\{dbName\}\.\$\{tableName\}<\/title>/);
  assert.match(source, /request="listRows"/);
  assert.match(source, /select="data"/);
  assert.match(source, /\$\{writable \? html`<button data-action="edit-row">Edit<\/button>` : nothing\}/);
  assert.match(source, /resolveRowId\(event\.detail\.resource, primaryKey\)/);
  assert.match(source, /navigate\('db-row-editor', \{ dbName, tableName, id \}\)/);
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'code'), 'A');
  assert.equal(helper.resolveRowId({ id:1, code:'A' }, 'missing'), '1');
  assert.equal(helper.resolveRowId({}, 'id'), '');
});

test('row editor is one row query plus generated data-form plus direct update operation', async () => {
  const source = await component('db-row-editor');
  assert.match(source, /<title>Edit — \$\{dbName\}\.\$\{tableName\} #\$\{id\}<\/title>/);
  assert.match(source, /name="id" type="string" required example="1"/);
  assert.match(source, /request="getRow"/);
  assert.match(source, /data-operation=updateRow/);
  assert.match(source, /db-name=\$\{dbName\}/);
  assert.match(source, /table-name=\$\{tableName\}/);
  assert.match(source, /id=\$\{id\}/);
  assert.match(source, /<data-form>[\s\S]*<button data-operation=updateRow>Save<\/button>[\s\S]*<\/data-form>/);
  assert.doesNotMatch(source, /getTableSchema|\bsave=/);
  assert.doesNotMatch(source, /form-input|data-table|data-action/);
  assert.doesNotMatch(source, /navigate\(/);
});
