import { LitElement, html, css } from 'lit';

class DbRowDetails extends LitElement {
  static properties = {
    dbName: { type: String },
    tableName: { type: String },
    id: { type: String },
    primaryKey: { type: String },
    writable: { type: Boolean },
    row: { state: true },
    loading: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    h3{margin:0 0 10px;font-size:14px}
    dl{display:grid;grid-template-columns:minmax(120px,auto) minmax(0,1fr);margin:0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden}
    dt,dd{margin:0;padding:7px 9px;border-bottom:1px solid #e5e7eb}dt{font-weight:650;background:#f8fafc;color:#475569}dd{overflow-wrap:anywhere}
    dl>:nth-last-child(-n+2){border-bottom:0}
    .actions{display:flex;gap:6px;margin-top:10px}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:5px 9px;cursor:pointer}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}
  `;

  constructor() {
    super();
    this.dbName = 'app';
    this.tableName = 'users';
    this.id = '1';
    this.primaryKey = 'id';
    this.writable = true;
    this.row = null;
    this.loading = false;
    this.error = '';
  }

  connectedCallback() {
    super.connectedCallback();
    void this.load();
  }

  updated(changed) {
    if ((changed.has('dbName') || changed.has('tableName') || changed.has('id')) && [...changed.values()].some(value => value !== undefined)) void this.load();
  }

  async load() {
    if (!this.dbName || !this.tableName || !this.id) {
      this.row = null;
      return;
    }
    this.loading = true;
    this.error = '';
    try {
      this.row = await Promise.resolve(api.getRow({ path: { dbName: this.dbName, tableName: this.tableName, id: this.id } }));
    } catch (error) {
      this.error = error?.message || String(error);
      this.row = null;
    } finally {
      this.loading = false;
    }
  }

  edit() {
    navigate('db-row-editor', {
      dbName: this.dbName,
      tableName: this.tableName,
      id: this.id,
      primaryKey: this.primaryKey,
      writable: this.writable
    });
  }

  back() {
    navigate('db-table-rows', {
      dbName: this.dbName,
      tableName: this.tableName,
      primaryKey: this.primaryKey,
      writable: this.writable
    });
  }

  render() {
    if (!this.id) return html`<div class="empty">No record loaded.</div>`;
    if (this.loading) return html`<div class="empty">Loading record…</div>`;
    if (this.error) return html`<div class="error">${this.error}</div>`;
    if (!this.row) return html`<div class="empty">No record loaded.</div>`;
    return html`
      <h3>${this.dbName}.${this.tableName} #${this.id}</h3>
      <dl>
        ${Object.entries(this.row).map(([field, value]) => html`<dt>${field}</dt><dd>${formatValue(value)}</dd>`)}
      </dl>
      <div class="actions">
        ${this.writable ? html`<button type="button" @click=${() => this.edit()}>Edit</button>` : ''}
        <button type="button" @click=${() => this.back()}>Back to rows</button>
      </div>
    `;
  }
}

function formatValue(value) {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'object') return JSON.stringify(value, null, 2);
  return String(value);
}

customElements.define('db-row-details', DbRowDetails);
