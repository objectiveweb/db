export function normalizeRowId(value) {
  const id = value == null ? '' : String(value).trim();
  if (!id) return '';
  const lowered = id.toLowerCase();
  return lowered === 'undefined' || lowered === 'null' ? '' : id;
}

export function rowFields(row) {
  return Object.entries(row || {}).map(([field, value]) => ({
    field,
    value: formatValue(value)
  }));
}

function formatValue(value) {
  if (value === undefined || value === null) return '';
  if (typeof value === 'object') {
    try { return JSON.stringify(value); }
    catch { return String(value); }
  }
  return String(value);
}
