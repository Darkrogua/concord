export const PRESET_COLORS = [
  { slug: 'blue', hex: '#3B82F6', label: 'Синий' },
  { slug: 'green', hex: '#22C55E', label: 'Зелёный' },
  { slug: 'amber', hex: '#F59E0B', label: 'Янтарный' },
  { slug: 'red', hex: '#EF4444', label: 'Красный' },
  { slug: 'violet', hex: '#8B5CF6', label: 'Фиолетовый' },
  { slug: 'teal', hex: '#14B8A6', label: 'Бирюзовый' },
  { slug: 'pink', hex: '#EC4899', label: 'Розовый' },
  { slug: 'gray', hex: '#6B7280', label: 'Серый' },
]

export const DEFAULT_PRESET_COLOR = PRESET_COLORS[0].slug

export function presetColorHex(slug) {
  const item = PRESET_COLORS.find((color) => color.slug === slug)
  return item?.hex || PRESET_COLORS[0].hex
}

export function isValidPresetColor(slug) {
  return PRESET_COLORS.some((color) => color.slug === slug)
}
