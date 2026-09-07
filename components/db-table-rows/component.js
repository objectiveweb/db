export function resolveRowId(row, primaryKey) {
  if (!row || typeof row !== 'object') return '';
  const keys = [primaryKey, 'id']
    .map(key => key == null ? '' : String(key).trim())
    .filter(Boolean);
  for (const key of [...new Set(keys)]) {
    const value = row[key];
    if (value !== undefined && value !== null && value !== '') return String(value);
  }
  return '';
}
