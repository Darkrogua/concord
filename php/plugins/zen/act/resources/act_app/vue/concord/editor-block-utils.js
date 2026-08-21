export const EDITOR_BLOCK_LABELS = {
  text: 'Текст',
  gallery: 'Галерея',
  files: 'Файлы',
  link: 'Ссылки',
  checkbox: 'Чеклист',
  code: 'Код',
}

function pluralizeRu(value, forms) {
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) {
    return forms[0]
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
    return forms[1]
  }
  return forms[2]
}

function stripPreviewText(value) {
  return String(value || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1')
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/^#{1,6}\s+/gm, '')
    .replace(/^>\s+/gm, '')
    .replace(/^[-*+]\s+/gm, '')
    .replace(/^\d+\.\s+/gm, '')
    .replace(/[*_~#>`[\]()\\-]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

export function getEditorBlockTypeLabel(block) {
  return EDITOR_BLOCK_LABELS[block?.type] || 'Блок'
}

export function getEditorBlockLabel(block) {
  const custom = String(block?.label || '').trim()
  if (custom) {
    return custom
  }
  return getEditorBlockTypeLabel(block)
}

export function renameEditorBlock(block, value) {
  if (!block) {
    return
  }
  const next = String(value ?? '')
  block.label = next
  if (block.type !== 'text' && block.type !== 'code') {
    block.title = next.trim() || getEditorBlockTypeLabel(block)
  }
}

export function getEditorBlockSummary(block) {
  if (!block) {
    return ''
  }

  switch (block.type) {
    case 'gallery': {
      const count = block.photos?.length || 0
      return `${count} ${pluralizeRu(count, ['фотография', 'фотографии', 'фотографий'])}`
    }
    case 'files': {
      const count = block.files?.length || 0
      return `${count} ${pluralizeRu(count, ['файл', 'файла', 'файлов'])}`
    }
    case 'link': {
      const count = block.links?.length || 0
      return `${count} ${pluralizeRu(count, ['ссылка', 'ссылки', 'ссылок'])}`
    }
    case 'text': {
      const label = getEditorBlockLabel(block)
      if (label && label !== getEditorBlockTypeLabel(block)) {
        return label
      }
      const title = block.title?.trim()
      if (title) {
        return title
      }
      const excerpt = stripPreviewText(block.content)
      if (excerpt) {
        return excerpt.length > 48 ? `${excerpt.slice(0, 48)}…` : excerpt
      }
      return 'Без текста'
    }
    default:
      return ''
  }
}

export function getTextBlockPreviewTitle(block) {
  return getEditorBlockLabel(block)
}

export function getTextBlockPreviewExcerpt(block, limit = 120) {
  const content = stripPreviewText(block?.content)
  if (!content) {
    return ''
  }
  return content.length > limit ? `${content.slice(0, limit)}…` : content
}

export function formatLinkDisplayUrl(value = '', maxLength = 42) {
  const raw = String(value || '').trim()
  if (!raw) {
    return ''
  }

  try {
    const parsed = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`)
    const path = parsed.pathname === '/' ? '' : parsed.pathname.replace(/\/$/, '')
    const display = `${parsed.hostname}${path}`
    return display.length > maxLength ? `${display.slice(0, maxLength - 1)}…` : display
  } catch {
    const display = raw.replace(/^https?:\/\//i, '').split(/[?#]/)[0].replace(/\/$/, '')
    return display.length > maxLength ? `${display.slice(0, maxLength - 1)}…` : display
  }
}

export function getLinkDisplayLabel(link) {
  const title = String(link?.title || '').trim()
  if (title) {
    return title
  }
  return formatLinkDisplayUrl(link?.url || '')
}
