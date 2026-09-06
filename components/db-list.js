import { LitElement, html, css } from 'lit';

class DbList extends LitElement {
  static properties = {
    databases: { state: true },
    loading: { state: true },
    error: { state: true }
  };

  static styles = css`
    :host{display:block;font:13px/1.4 system-ui,sans-serif;color:#1f2937}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{padding:7px 8px;border-bottom:1px solid #e5e7eb;text-align:left}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:5px;padding:4px 8px;cursor:pointer}
    .empty,.error{padding:12px;color:#64748b}.error{color:#b42318}
  `;

  constructor() {
    super();
    this.databases = [];
    this.loading = false;
    this.error = '';
  }

  connectedCallback() {
    super.connectedCallback();
    void this.load();
  }

  async load() {
    this.loading = true;
    this.error = '';
    try {
      this.databases = await Promise.resolve(api.listDatabases());
    } catch (error) {
      this.error = error?.message || String(error);
      this.databases = [];
    } finally {
      this.loading = false;
    }
  }

  render() {
    if (this.loading) return html`<div class="empty">Loading databases…</div>`;
    if (this.error) return html`<div class="error">${this.error}</div>`;
    if (!this.databases.length) return html`<div class="empty">No databases found.</div>`;
    return html`
      <table>
        <thead><tr><th>Database</th><th></th></tr></thead>
        <tbody>
          ${this.databases.map(database => html`
            <tr>
              <td>${database.name}</td>
              <td><button type="button" @click=${() => navigate('db-table-list', { dbName: database.name })}>Open</button></td>
            </tr>
          `)}
        </tbody>
      </table>
    `;
  }
}

customElements.define('db-list', DbList);
