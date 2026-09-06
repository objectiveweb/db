import { LitElement, html, css } from 'lit';

const INVALIDATE_EVENT = 'metaproject-data-invalidate';

class DbRowEditor extends LitElement {
  static properties = {
    dbName: { type: String },
    tableName: { type: String },
    id: { type: String },
    primaryKey: { type: String },
    writable: { type: Boolean },
    schema: { state: true },
    row: { state: true },
    form: { state: true },
    loading: { state: true },
    saving: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    h3{margin:0 0 10px;font-size:14px}
    form{display:grid;gap:10px;max-width:40rem}
    label{display:grid;gap:4px}.label{font-size:11px;font-weight:650;color:#475569}
    input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:6px;padding:7px 8px;font:inherit}
    label.checkbox{display:flex;align-items:center;gap:7px}.checkbox input{width:auto}
    .actions{display:flex;gap:6px;margin-top:4px}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:6px 10px;cursor:pointer}button.primary{background:#3157d5;border-color:#3157d5;color:#fff}button:disabled{opacity:.55;cursor:default}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}.readonly{padding:10px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;color:#64748b}
  `;

  constructor() {
    super();
    this.dbName = 'app';
    this.tableName = 'users';
    this.id = '';
    this.primaryKey = 'id';
    this.writable = true;
    this.schema = null;
    this.row = null;
    this.form = {};
    this.loading = false;
    this.saving = false;
    this.error = '';
  }

  connectedCallback() {
    super.connectedCallback();
    if (this.id) void this.load();
  }

  updated(changed) {
    if ((changed.has('dbName') || changed.has('tableName') || changed.has('id')) && [...changed.values()].some(value => value !== undefined)) {
      if (this.id) void this.load();
      else {
        this.schema = null;
        this.row = null;
        this.form = {};
      }
    }
  }

  async load() {
    if (!this.dbName || !this.tableName || !this.id) return;
    this.loading = true;
    this.error = '';
    try {
      const [schema, row] = await Promise.all([
        Promise.resolve(api.getTableSchema({ path: { dbName: this.dbName, tableName: this.tableName } })),
        Promise.resolve(api.getRow({ path: { dbName: this.dbName, tableName: this.tableName, id: this.id } }))
      ]);
      this.schema = schema;
      this.row = row;
      this.writable = Boolean(schema?.writable);
      this.primaryKey = schema?.primaryKey || this.primaryKey;
      this.form = { ...row };
    } catch (error) {
      this.error = error?.message || String(error);
      this.schema = null;
      this.row = null;
      this.form = {};
    } finally {
      this.loading = false;
    }
  }

  writableColumns() {
    return (this.schema?.columns || []).filter(column => column.writable);
  }

  setField(column, event) {
    let value;
    if (column.type === 'boolean') value = event.currentTarget.checked;
    else if (isNumeric(column.type)) value = event.currentTarget.value === '' ? null : Number(event.currentTarget.value);
    else value = event.currentTarget.value;
    this.form = { ...this.form, [column.name]: value };
  }

  async save(event) {
    event.preventDefault();
    if (!this.schema || !this.row || !this.writable) return;
    this.saving = true;
    this.error = '';
    try {
      const body = Object.fromEntries(this.writableColumns().map(column => [column.name, this.form[column.name]]));
      await Promise.resolve(api.updateRow({ path: { dbName: this.dbName, tableName: this.tableName, id: this.id }, body }));
      window.dispatchEvent(new CustomEvent(INVALIDATE_EVENT, {
        detail: {
          keys: [`db:${this.dbName}:${this.tableName}:row:${this.id}`, `db:${this.dbName}:${this.tableName}:rows`],
          broadcast: true
        }
      }));
      navigate('db-row-details', {
        dbName: this.dbName,
        tableName: this.tableName,
        id: this.id,
        primaryKey: this.primaryKey,
        writable: this.writable
      });
    } catch (error) {
      this.error = error?.message || String(error);
    } finally {
      this.saving = false;
    }
  }

  cancel() {
    navigate('db-row-details', {
      dbName: this.dbName,
      tableName: this.tableName,
      id: this.id,
      primaryKey: this.primaryKey,
      writable: this.writable
    });
  }

  renderField(column) {
    const value = this.form[column.name];
    if (column.type === 'boolean') {
      return html`<label class="checkbox"><input type="checkbox" .checked=${Boolean(value)} @change=${event => this.setField(column, event)}><span>${column.name}</span></label>`;
    }
    return html`<label>
      <span class="label">${column.name}</span>
      <input
        type=${isNumeric(column.type) ? 'number' : inputType(column.type)}
        .value=${value == null ? '' : String(value)}
        ?required=${!column.nullable && column.default == null}
        @input=${event => this.setField(column, event)}
      >
    </label>`;
  }

  render() {
    if (!this.id) return html`<div class="empty" role="status">No record loaded.</div>`;
    if (this.loading) return html`<div class="empty">Loading record…</div>`;
    if (this.error && !this.row) return html`<div class="error">${this.error}</div>`;
    if (!this.schema || !this.row) return html`<div class="empty">No record loaded.</div>`;
    const fields = this.writableColumns();
    return html`
      <h3>Edit ${this.dbName}.${this.tableName} #${this.id}</h3>
      ${this.error ? html`<div class="error">${this.error}</div>` : ''}
      ${this.writable ? html`
        <form @submit=${event => this.save(event)}>
          ${fields.map(column => this.renderField(column))}
          ${fields.length ? '' : html`<div class="readonly">This table has no writable fields.</div>`}
          <div class="actions">
            <button class="primary" type="submit" ?disabled=${this.saving || !fields.length}>${this.saving ? 'Saving…' : 'Save'}</button>
            <button type="button" @click=${() => this.cancel()}>Cancel</button>
          </div>
        </form>
      ` : html`<div class="readonly">This table is read-only.</div>`}
    `;
  }
}

function isNumeric(type) {
  return ['integer','bigint','float','double','decimal','number'].includes(String(type || '').toLowerCase());
}

function inputType(type) {
  const value = String(type || '').toLowerCase();
  if (value === 'date') return 'date';
  if (value === 'datetime' || value === 'datetime-local') return 'datetime-local';
  if (value === 'email') return 'email';
  return 'text';
}

customElements.define('db-row-editor', DbRowEditor);
