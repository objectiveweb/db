import { LitElement, html, css } from 'lit';

class DbTableList extends LitElement {
  static properties = {
    dbName: { type: String },
    tables: { state: true },
    loading: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    h3{margin:0 0 10px;font-size:14px}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{padding:7px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .actions{display:flex;gap:5px;justify-content:flex-end}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:4px 8px;cursor:pointer}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}
  `;

  constructor() {
    super();
    this.dbName = 'app';
    this.tables = [];
    this.loading = false;
    this.error = '';
  }

  connectedCallback() {
    super.connectedCallback();
    void this.load();
  }

  updated(changed) {
    if (changed.has('dbName') && changed.get('dbName') !== undefined) void this.load();
  }

  async load() {
    if (!this.dbName) return;
    this.loading = true;
    this.error = '';
    try {
      this.tables = await Promise.resolve(api.listTables({ path: { dbName: this.dbName } }));
    } catch (error) {
      this.error = error?.message || String(error);
      this.tables = [];
    } finally {
      this.loading = false;
    }
  }

  openSchema(table) {
    navigate('db-table-schema', { dbName: this.dbName, tableName: table.name });
  }

  openRows(table) {
    navigate('db-table-rows', {
      dbName: this.dbName,
      tableName: table.name,
      primaryKey: table.primaryKey || '',
      writable: Boolean(table.writable)
    });
  }

  render() {
    if (!this.dbName) return html`<div class="empty">No database selected.</div>`;
    if (this.loading) return html`<div class="empty">Loading tables…</div>`;
    if (this.error) return html`<div class="error">${this.error}</div>`;
    return html`
      <h3>Database: ${this.dbName}</h3>
      ${this.tables.length ? html`
        <table>
          <thead><tr><th>Table</th><th>Primary key</th><th>Writable</th><th></th></tr></thead>
          <tbody>
            ${this.tables.map(table => html`
              <tr>
                <td>${table.name}</td>
                <td>${table.primaryKey || '—'}</td>
                <td>${table.writable ? 'yes' : 'no'}</td>
                <td class="actions">
                  <button type="button" @click=${() => this.openSchema(table)}>Schema</button>
                  <button type="button" @click=${() => this.openRows(table)}>Rows</button>
                </td>
              </tr>
            `)}
          </tbody>
        </table>
      ` : html`<div class="empty">No tables found.</div>`}
    `;
  }
}

customElements.define('db-table-list', DbTableList);
