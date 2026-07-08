export const BLOCK_TYPES = [
  {
    type: 'text',
    title: 'Текст',
    description: 'Простой текстовый блок — строка или абзац',
  },
  {
    type: 'markdown',
    title: 'Markdown',
    description: 'Форматированный текст с поддержкой Markdown',
  },
  {
    type: 'webapp',
    title: 'Web-приложение',
    description: 'HTML-код мини-приложения внутри акта',
  },
  {
    type: 'checklist',
    title: 'Чеклист',
    description: 'Список пунктов для согласования и подписей',
  },
  {
    type: 'gallery',
    title: 'Фотогалерея',
    description: 'Изображения с каруселью и полноэкранным просмотром',
  },
]

export function blockTypeLabel(type) {
  return BLOCK_TYPES.find((item) => item.type === type)?.title || type
}

export function defaultBlockPayload(type) {
  if (type === 'webapp') {
    return { type: 'webapp', html: '' }
  }
  if (type === 'checklist') {
    return { type: 'checklist', description: '', items: [] }
  }
  if (type === 'gallery') {
    return { type: 'gallery', items: [] }
  }
  if (type === 'markdown') {
    return { type: 'markdown', markdown: '' }
  }

  return { type: 'text', text: '' }
}

export function defaultBlockName(type) {
  return blockTypeLabel(type)
}
