export const EDITOR_BLOCK_TYPES = [
  { id: 'text', label: 'Текст' },
  { id: 'gallery', label: 'Галерея' },
  { id: 'files', label: 'Файлы' },
  { id: 'links', label: 'Ссылки' },
  { id: 'code', label: 'Код' },
]

export const EDITOR_BLOCK_LABELS = {
  text: 'Текст',
  gallery: 'Галерея',
  files: 'Файлы',
  links: 'Ссылки',
  code: 'Код',
}

function pluralizeRu(value, forms) {
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) return forms[0]
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return forms[1]
  return forms[2]
}

export function getEditorBlockTypeLabel(block) {
  return EDITOR_BLOCK_LABELS[block?.type] || 'Блок'
}

export function getEditorBlockLabel(block) {
  const custom = String(block?.label || block?.title || '').trim()
  if (custom) return custom
  return getEditorBlockTypeLabel(block)
}

export function getEditorBlockSummary(block) {
  if (!block) return ''

  switch (block.type) {
    case 'gallery': {
      const count = block.content?.items?.length || 0
      return `${count} ${pluralizeRu(count, ['фото', 'фото', 'фото'])}`
    }
    case 'files': {
      const count = block.files?.length || 0
      return `${count} ${pluralizeRu(count, ['файл', 'файла', 'файлов'])}`
    }
    case 'links': {
      const count = block.content?.links?.length || 0
      return `${count} ${pluralizeRu(count, ['ссылка', 'ссылки', 'ссылок'])}`
    }
    case 'code':
      return block.content?.body ? 'Есть код' : 'Пусто'
    default:
      return block.content?.body ? 'Есть текст' : 'Пусто'
  }
}

export function defaultBlockContent(type) {
  switch (type) {
    case 'links':
      return { links: [] }
    case 'gallery':
      return { items: [] }
    case 'code':
      return { body: '' }
    default:
      return { body: '' }
  }
}

export function normalizeBlock(block) {
  const content = { ...defaultBlockContent(block.type), ...(block.content || {}) }
  if (block.type === 'links' && !Array.isArray(content.links)) content.links = []
  if (block.type === 'gallery' && !Array.isArray(content.items)) content.items = []
  return {
    ...block,
    content,
    files: block.files || [],
    label: block.label || block.title || '',
  }
}
