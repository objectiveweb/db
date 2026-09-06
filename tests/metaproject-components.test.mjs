import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

async function component(name){return readFile(path.join('components',name,'component.html'),'utf8');}

test('database table and rows components expose schema and row collection separately',async()=>{
  const table=await component('db-table-workspace');
  const rows=await component('db-table-rows');
  assert.match(table,/api\.getTableSchema/);
  assert.match(table,/<db-table-schema/);
  assert.match(rows,/api\.getTableSchema/);
  assert.match(rows,/<db-table-row-data/);
  const data=await component('db-table-row-data');
  assert.match(data,/api\.listRows/);
  assert.match(data,/\.key=\$\{`db:\$\{dbName\}:\$\{tableName\}:rows`\}/);
});

test('generic row table supports runtime columns and optional row actions',async()=>{
  const source=await component('db-row-list');
  assert.match(source,/\.columns=\$\{/);
  assert.match(source,/\.actions=\$\{/);
  assert.match(source,/viewable/);
  assert.match(source,/editable/);
  assert.match(source,/deletable/);
});

test('row details edit and create are separate showcaseable components',async()=>{
  const details=await component('db-row-details');
  const detailsView=await component('db-row-details-view');
  const edit=await component('db-row-editor');
  const create=await component('db-row-create');
  assert.match(details,/api\.getRow/);
  assert.match(detailsView,/<dl class="fields">/);
  assert.match(edit,/api\.updateRow/);
  assert.match(edit,/invalidate\(\[/);
  assert.match(create,/api\.createRow/);
  assert.match(create,/invalidate\(`db:/);
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
