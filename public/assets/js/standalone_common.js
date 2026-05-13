/**
 * Shared helpers for standalone view pages.
 */

const PALETTE = [
  { bg:'#eef2ff',border:'#818cf8',dot:'#6366f1',text:'#3730a3',avatar:'#6366f1' },
  { bg:'#f0fdf4',border:'#34d399',dot:'#10b981',text:'#065f46',avatar:'#10b981' },
  { bg:'#fff7ed',border:'#fb923c',dot:'#f97316',text:'#9a3412',avatar:'#f97316' },
  { bg:'#fdf4ff',border:'#c084fc',dot:'#a855f7',text:'#6b21a8',avatar:'#a855f7' },
  { bg:'#eff6ff',border:'#60a5fa',dot:'#3b82f6',text:'#1e40af',avatar:'#3b82f6' },
  { bg:'#fff1f2',border:'#fb7185',dot:'#f43f5e',text:'#9f1239',avatar:'#f43f5e' },
  { bg:'#ecfdf5',border:'#6ee7b7',dot:'#059669',text:'#064e3b',avatar:'#059669' },
  { bg:'#fefce8',border:'#fde047',dot:'#ca8a04',text:'#713f12',avatar:'#ca8a04' },
];

function seedColor(id) {
  return PALETTE[parseInt(id || 0, 10) % PALETTE.length];
}

function initials(name) {
  return (name || '?').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

const acCache = {};
function acFetch(key, apiFn, force = false) {
  if (!force && acCache[key]) return $.Deferred().resolve(acCache[key]).promise();
  return apiFn().done(d => { acCache[key] = d; });
}

