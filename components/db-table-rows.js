import { LitElement, html, css } from 'lit';

const INVALIDATE_EVENT = 'metaproject-data-invalidate';

class DbTableRows extends LitElement {
  static properties = {
    dbName: { type: String },
    tableName: { type: String },
    primaryKey: { type: String },
    writable: { type: Boolean },
    page: { state: true },
    loading: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    .head{display:flex;align-items:center;gap:8px;margin-bottom:8px}.head strong{flex:1}
    .table-wrap{overflow:auto;max-width:100%}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{padding:6px 7px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;white-space:nowrap}
    th{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .row-actions{display:flex;gap:5px}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:4px 8px;cursor:pointer}
    .meta{margin-top:8px;font-size:11px;color:#64748b}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}
  `;

  constructor() {
    super();
    this.dbName = 'app';
    this.tableName = 'users';
    this.primaryKey = 'id';
    this.writable = true;
    this.page = { data: [], total: 0, offset: 0, limit: 25 };
    this.loading = false;
    this.error = '';
    this.onInvalidate = event => {
      const keys = Array.isArray(event.detail?.keys) ? event.detail.keys : [];
      if (keys.includes(this.dataKey)) void this.load();
    };
  }

  get dataKey() {
    return `db:${this.dbName}:${this.tableName}:rows`;
  }

  connectedCallback() {
    super.connectedCallback();
    window.addEventListener(INVALIDATE_EVENT, this.onInvalidate);
    void this.load();
  }

  disconnectedCallback() {
    window.removeEventListener(INVALIDATE_EVENT, this.onInvalidate);
    super.disconnectedCallback();
  }

  updated(changed) {
    if ((changed.has('dbName') || changed.has('tableName')) && [...changed.values()].some(value => value !== undefined)) void this.load();
  }

  async load() {
    if (!this.dbName || !this.tableName) return;
    this.loading = true;
    this.error = '';
    try {
      this.page = await Promise.resolve(api.listRows({ path: { dbName: this.dbName, tableName: this.tableName } }));
    } catch (error) {
      this.error = error?.message || String(error);
      this.page = { data: [], total: 0, offset: 0, limit: 25 };
    } finally {
      this.loading = false;
    }
  }

  rowId(row) {
    return this.primaryKey ? row?.[this.primaryKey] : undefined;
  }

  viewRow(row) {
    const id = this.rowId(row);
    if (id === undefined || id === null) return;
    navigate('db-row-details', {
      dbName: this.dbName,
      tableName: this.tableName,
      id: String(id),
      primaryKey: this.primaryKey,
      writable: this.writable
    });
  }

  editRow(row) {
    const id = this.rowId(row);
    if (id === undefined || id === null) return;
    navigate('db-row-editor', {
      dbName: this.dbName,
      tableName: this.tableName,
      id: String(id),
      primaryKey: this.primaryKey,
      writable: this.writable
    });
  }

  render() {
    if (!this.dbName || !this.tableName) return html`<div class="empty">No table selected.</div>`;
    if (this.loading) return html`<div class="empty">Loading rows…</div>`;
    if (this.error) return html`<div class="error">${this.error}</div>`;
    const rows = Array.isArray(this.page?.data) ? this.page.data : [];
    const columns = rows.length ? Object.keys(rows[0]) : [];
    return html`
      <div class="head">
        <strong>${this.dbName}.${this.tableName}</strong>
        <button type="button" @click=${() => this.load()}>Refresh</button>
      </div>
      ${rows.length ? html`
        <div class="table-wrap">
          <table>
            <thead><tr>${columns.map(column => html`<th>${column}</th>`)}${this.primaryKey ? html`<th>Actions</th>` : ''}</tr></thead>
            <tbody>
              ${rows.map(row => html`
                <tr>
                  ${columns.map(column => html`<td>${formatValue(row[column])}</td>`)}
                  ${this.primaryKey ? html`
                    <td class="row-actions">
                      <button type="button" @click=${() => this.viewRow(row)}>View</button>
                      ${this.writable ? html`<button type="button" @click=${() => this.editRow(row)}>Edit</button>` : ''}
                    </td>
                  ` : ''}
                </tr>
              `)}
            </tbody>
          </table>
        </div>
        <div class="meta">${this.page.total ?? rows.length} rows</div>
      ` : html`<div class="empty">No rows found.</div>`}
    `;
  }
}

function formatValue(value) {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

customElements.define('db-table-rows', DbTableRows);
