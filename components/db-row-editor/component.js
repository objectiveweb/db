export function normalizeRowId(value) {
  const id = value == null ? '' : String(value).trim();
  if (!id) return '';
  const lowered = id.toLowerCase();
  return lowered === 'undefined' || lowered === 'null' ? '' : id;
}

export function editableFields(schema, row) {
  return (schema?.columns || [])
    .filter(column => column.writable)
    .map(column => ({
      name: column.name,
      label: column.name,
      type: column.type,
      value: row?.[column.name] ?? ''
    }));
}

export function rowBody(fields) {
  return Object.fromEntries((fields || []).map(field => [field.name, coerceValue(field.value, field.type)]));
}

function coerceValue(value, type) {
  const normalized = String(type || '').toLowerCase();
  if (normalized === 'boolean') {
    if (typeof value === 'boolean') return value;
    return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
  }
  if (['integer', 'bigint', 'float', 'double', 'decimal', 'number'].includes(normalized)) {
    return value === '' || value === null || value === undefined ? null : Number(value);
  }
  return value;
}
