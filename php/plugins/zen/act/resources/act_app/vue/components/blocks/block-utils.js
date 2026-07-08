export function formatHtmlSizeMb(html) {
  const bytes = new TextEncoder().encode(String(html || '')).length
  const mb = bytes / (1024 * 1024)

  if (mb < 0.1 && bytes > 0) {
    return '< 0.1 Мб'
  }

  return `${mb.toFixed(1)} Мб`
}
