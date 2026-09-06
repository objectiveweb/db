import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

async function component(name){return readFile(path.join('components',name,'component.html'),'utf8');}

test('database table shows schema while database table rows fetches only row data',async()=>{
  const table=await component('db-table-workspace');
  const rows=await component('db-table-rows');
  assert.match(table,/api\.getTableSchema/);
  assert.match(table,/<db-table-schema/);
  assert.match(rows,/api\.listRows/);
  assert.doesNotMatch(rows,/api\.getTableSchema/);
  assert.match(rows,/<db-row-list/);
  assert.match(rows,/primaryKey/);
  assert.match(rows,/writable/);
});

test('table navigation passes selected table state to schema and row components',async()=>{
  const source=await component('db-table-list');
  assert.match(source,/navigate\('db-table-workspace', \{ dbName, tableName: event\.detail\.resource\.name \}\)/);
  assert.match(source,/navigate\('db-table-rows'/);
  assert.match(source,/primaryKey: event\.detail\.resource\.primaryKey/);
  assert.match(source,/writable: Boolean\(event\.detail\.resource\.writable\)/);
});

test('generic row table derives fields from returned rows and exposes optional actions',async()=>{
  const source=await component('db-row-list');
  assert.doesNotMatch(source,/\.columns=\$\{/);
  assert.match(source,/\.resource=\$\{resource\?\.data \|\| \[\]\}/);
  assert.match(source,/\.actions=\$\{/);
  assert.match(source,/viewable/);
  assert.match(source,/editable/);
  assert.match(source,/deletable/);
});

test('row editor stays empty until navigation supplies a record id',async()=>{
  const edit=await component('db-row-editor');
  assert.match(edit,/name="id" type="string" default=""/);
  assert.doesNotMatch(edit,/name="id"[^>]*example=/);
  assert.match(edit,/No record loaded\./);
  assert.match(edit,/id \? html`/);
  assert.match(edit,/api\.getRow/);
  assert.match(edit,/api\.updateRow/);
});

test('row details edit and create keep reactive row-list invalidation',async()=>{
  const details=await component('db-row-details');
  const detailsView=await component('db-row-details-view');
  const create=await component('db-row-create');
  assert.match(details,/api\.getRow/);
  assert.match(details,/primaryKey: schema\?\.primaryKey/);
  assert.match(detailsView,/<dl class="fields">/);
  assert.match(create,/api\.createRow/);
  assert.match(create,/invalidate\(`db:/);
  assert.match(create,/primaryKey: schema\?\.primaryKey/);
});

test('all native data-row templates remain static',async()=>{
  for(const name of await readdir('components')){
    let source;
    try{source=await component(name);}catch{continue;}
    for(const match of source.matchAll(/<template\b[^>]*>([\s\S]*?)<\/template\s*>/gi)){
      assert.equal(match[1].includes('${'),false,`${name} contains a Lit expression inside native template`);
    }
  }
});
