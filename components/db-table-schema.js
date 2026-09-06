import { LitElement, html, css } from 'lit';

class DbTableSchema extends LitElement {
  static properties = {
    dbName: { type: String },
    tableName: { type: String },
    schema: { state: true },
    loading: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    .summary{display:flex;gap:14px;flex-wrap:wrap;margin:0 0 10px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{padding:6px 7px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}
    th{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .toolbar{margin-top:10px;display:flex;gap:6px}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:5px 9px;cursor:pointer}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}
    .table-wrap{overflow:auto;max-width:100%}
  `;

  constructor() {
    super();
    this.dbName = 'app';
    this.tableName = 'users';
    this.schema = null;
    this.loading = false;
    this.error = '';
  }

  connectedCallback() {
    super.connectedCallback();
    void this.load();
  }

  updated(changed) {
    if ((changed.has('dbName') || changed.has('tableName')) && [...changed.values()].some(value => value !== undefined)) void this.load();
  }

  async load() {
    if (!this.dbName || !this.tableName) return;
    this.loading = true;
    this.error = '';
    try {
      this.schema = await Promise.resolve(api.getTableSchema({ path: { dbName: this.dbName, tableName: this.tableName } }));
    } catch (error) {
      this.error = error?.message || String(error);
      this.schema = null;
    } finally {
      this.loading = false;
    }
  }

  openRows() {
    navigate('db-table-rows', {
      dbName: this.dbName,
      tableName: this.tableName,
      primaryKey: this.schema?.primaryKey || '',
      writable: Boolean(this.schema?.writable)
    });
  }

  render() {
    if (!this.dbName || !this.tableName) return html`<div class="empty">No table selected.</div>`;
    if (this.loading) return html`<div class="empty">Loading schema…</div>`;
    if (this.error) return html`<div class="error">${this.error}</div>`;
    if (!this.schema) return html`<div class="empty">No schema loaded.</div>`;
    const columns = this.schema.columns || [];
    return html`
      <div class="summary">
        <span><strong>${this.dbName}.${this.schema.name || this.tableName}</strong></span>
        <span>Primary key: <code>${this.schema.primaryKey || 'none'}</code></span>
        <span>Writable: ${this.schema.writable ? 'yes' : 'no'}</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th><th>Auto</th><th>Primary</th><th>Writable</th></tr></thead>
          <tbody>
            ${columns.map(column => html`
              <tr>
                <td>${column.name}</td>
                <td>${column.type}</td>
                <td>${column.nullable ? 'yes' : 'no'}</td>
                <td>${formatValue(column.default)}</td>
                <td>${column.autoIncrement ? 'yes' : 'no'}</td>
                <td>${column.primary ? 'yes' : 'no'}</td>
                <td>${column.writable ? 'yes' : 'no'}</td>
              </tr>
            `)}
          </tbody>
        </table>
      </div>
      <div class="toolbar"><button type="button" @click=${() => this.openRows()}>Rows</button></div>
    `;
  }
}

function formatValue(value) {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

customElements.define('db-table-schema', DbTableSchema);
