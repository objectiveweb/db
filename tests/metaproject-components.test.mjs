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

test('DB exposes exactly five reusable HTML-first components', async () => {
  const entries = await readdir('components', { withFileTypes:true });
  const directories = entries.filter(entry => entry.isDirectory()).map(entry => entry.name).sort();
  assert.deepEqual(directories, [...expected].sort());
  assert.equal(entries.some(entry => entry.isFile() && /\.(?:js|ts)$/.test(entry.name)), false);
});

test('DB components use declarative data primitives and keep composition outside component code', async () => {
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
    assert.doesNotMatch(source, /navigate\(/, `${name} must leave Canvas composition outside the component`);
  }
});

test('database and table lists expose reusable semantic open events', async () => {
  const databases = await component('db-list');
  const tables = await component('db-table-list');
  assert.match(databases, /<data-source request="listDatabases">/);
  assert.match(databases, /<button data-action="open">Open<\/button>/);
  assert.match(tables, /<title>Tables — \$\{dbName\}<\/title>/);
  assert.match(tables, /request="listTables"/);
  assert.match(tables, /db-name=\$\{dbName\}/);
  assert.match(tables, /<button data-action="open">Open<\/button>/);
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
});

test('rows panel selects page.data and emits semantic Edit only for writable rows', async () => {
  const source = await component('db-table-rows');
  assert.match(source, /<title>Rows — \$\{dbName\}\.\$\{tableName\}<\/title>/);
  assert.match(source, /request="listRows"/);
  assert.match(source, /select="data"/);
  assert.match(source, /\$\{writable \? html`<button data-action="edit">Edit<\/button>` : nothing\}/);
});

test('Canvas owns DB open/edit composition and property mapping', async () => {
  const source = await readFile('metaproject.yaml','utf8');
  assert.match(source, /ref: db-list[\s\S]*?on:[\s\S]*?open:[\s\S]*?target: db-table-list/);
  assert.match(source, /ref: db-table-list[\s\S]*?target: db-table-schema[\s\S]*?target: db-table-rows/);
  assert.match(source, /dbName: \{ from: source\.dbName \}/);
  assert.match(source, /tableName: \{ from: event\.resource\.name \}/);
  assert.match(source, /primaryKey: \{ from: event\.resource\.primaryKey, default: id \}/);
  assert.match(source, /ref: db-table-rows[\s\S]*?edit:[\s\S]*?target: db-row-editor/);
  assert.match(source, /id: \{ from: event\.resource\[source\.primaryKey\] \}/);
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
});
