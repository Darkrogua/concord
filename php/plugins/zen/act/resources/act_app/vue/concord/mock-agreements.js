export const DEFAULT_TOP_TABS = [
  { id: 'agreements', label: 'Согласования', badge: 5 },
  { id: 'drafts', label: 'Черновики' },
  { id: 'favorites', label: 'Избранное', badge: 2 },
  { id: 'archive', label: 'Архив', badge: 2 },
  { id: 'work', label: 'Работа', badge: 2 },
]

export const SORT_OPTIONS = [
  { id: 'favorites', label: 'Избранным' },
  { id: 'date', label: 'Дате' },
  { id: 'newest', label: 'Сначала новые' },
  { id: 'activity', label: 'По активности' },
]

/** @typedef {'title' | 'content'} SearchScopeId */

export const SEARCH_SCOPE_OPTIONS = [
  { id: 'title', label: 'Название' },
  { id: 'content', label: 'Название и содержимое' },
]

export const FILTER_CATEGORIES = [
  { id: 'urgency', label: 'Срочность' },
  { id: 'created', label: 'Создано' },
  { id: 'participant', label: 'Участник' },
  { id: 'status', label: 'Статус' },
]

export const URGENCY_OPTIONS = [
  { id: 'set', label: 'Установлена' },
  { id: 'unset', label: 'Не установлена' },
]

/** @typedef {'today' | 'yesterday' | 'last_7d' | 'last_30d' | 'less_than' | 'exactly' | 'more_than' | 'after' | 'on_day' | 'before'} CreatedOptionId */

/**
 * @typedef {Object} CreatedOption
 * @property {CreatedOptionId} id
 * @property {string} label
 * @property {boolean} needsDate
 */

/** @type {CreatedOption[]} */
export const CREATED_OPTIONS = [
  { id: 'today', label: 'сегодня', needsDate: false },
  { id: 'yesterday', label: 'вчера', needsDate: false },
  { id: 'last_7d', label: 'последние 7 дней', needsDate: false },
  { id: 'last_30d', label: 'последние 30 дней', needsDate: false },
  { id: 'less_than', label: 'меньше чем', needsDate: true },
  { id: 'exactly', label: 'ровно', needsDate: true },
  { id: 'more_than', label: 'больше чем', needsDate: true },
  { id: 'after', label: 'после', needsDate: true },
  { id: 'on_day', label: 'в день', needsDate: true },
  { id: 'before', label: 'до', needsDate: true },
]

/**
 * @param {CreatedOption} option
 * @param {string} [date]
 */
export function formatCreatedFilterLabel(option, date = '') {
  const presets = {
    today: 'Создана: сегодня',
    yesterday: 'Создана: вчера',
    last_7d: 'Создана: последние 7 дней',
    last_30d: 'Создана: последние 30 дней',
  }

  if (presets[option.id]) {
    return presets[option.id]
  }

  const withDate = {
    less_than: 'Создана: меньше чем',
    exactly: 'Создана: ровно',
    more_than: 'Создана: больше чем',
    after: 'Создана: после',
    on_day: 'Создана: в день',
    before: 'Создана: до',
  }

  const prefix = withDate[option.id]
  return date ? `${prefix} ${date}` : prefix
}

export function formatDateRu(date = new Date()) {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  return `${day}.${month}.${year}`
}

export function formatIsoDateToRu(value = '') {
  if (!value) {
    return ''
  }
  const [year, month, day] = value.split('-')
  if (!year || !month || !day) {
    return value
  }
  return `${day}.${month}.${year}`
}

function parseRuDateToDate(value = '') {
  const [day, month, year] = String(value || '').trim().split('.')
  if (!day || !month || !year) {
    return null
  }
  const date = new Date(Number(year), Number(month) - 1, Number(day))
  return Number.isNaN(date.getTime()) ? null : date
}

export function formatAgreementDateGroup(dateValue) {
  const date = parseRuDateToDate(dateValue)
  if (!date) {
    return 'Ранее'
  }

  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diffDays = Math.round((today - target) / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return 'Сегодня'
  }
  if (diffDays === 1) {
    return 'Вчера'
  }
  if (diffDays === 2) {
    return 'Позавчера'
  }

  const months = [
    'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
  ]
  const day = date.getDate()
  const month = months[date.getMonth()]
  return `${day} ${month}`
}

export function groupAgreementsByDate(agreements) {
  const groups = new Map()
  for (const agreement of agreements) {
    const key = formatAgreementDateGroup(agreement.createdAt)
    if (!groups.has(key)) {
      groups.set(key, [])
    }
    groups.get(key).push(agreement)
  }
  const order = Array.from(groups.keys())
  order.sort((a, b) => {
    const dateA = parseRuDateToDate(groups.get(a)[0].createdAt)
    const dateB = parseRuDateToDate(groups.get(b)[0].createdAt)
    if (!dateA || !dateB) {
      return 0
    }
    return dateB - dateA
  })
  return order.map((label) => ({ label, items: groups.get(label) }))
}

function normalizeLegacySectionTitle(title) {
  const text = String(title || '').trim()
  if (!text) {
    return 'Раздел'
  }
  if (/^контейнер$/i.test(text)) {
    return 'Раздел'
  }
  if (/^новый контейнер$/i.test(text)) {
    return 'Новый раздел'
  }
  const numbered = text.match(/^контейнер\s+(\d+)$/i)
  if (numbered) {
    return `Раздел ${numbered[1]}`
  }
  return text
}

export function formatSectionTabTitle(title, maxWords = 2) {
  const text = normalizeLegacySectionTitle(title)
  if (!text) {
    return 'Раздел'
  }
  const words = text.split(/\s+/).filter(Boolean)
  if (words.length <= maxWords) {
    return text
  }
  return `${words.slice(0, maxWords).join(' ')}…`
}

export function parseRuDate(value = '') {
  const [day, month, year] = String(value || '').trim().split('.')
  if (!day || !month || !year) {
    return null
  }
  const date = new Date(Number(year), Number(month) - 1, Number(day))
  return Number.isNaN(date.getTime()) ? null : date
}

export function pluralizeDays(count) {
  const value = Math.abs(Number(count) || 0)
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) {
    return `${value} день`
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
    return `${value} дня`
  }
  return `${value} дней`
}

export function formatAgreementDaysLabel(startValue, endValue) {
  const start = parseRuDate(startValue)
  const end = parseRuDate(endValue)
  if (!start || !end) {
    return '—'
  }
  const msPerDay = 24 * 60 * 60 * 1000
  const diffDays = Math.round((end.getTime() - start.getTime()) / msPerDay)
  if (diffDays < 0) {
    return '—'
  }
  return pluralizeDays(diffDays)
}

export function getAgreementDaysRemaining(deadlineValue, today = new Date()) {
  const end = parseRuDate(deadlineValue)
  if (!end) {
    return null
  }
  const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  const endStart = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  const msPerDay = 24 * 60 * 60 * 1000
  return Math.round((endStart.getTime() - todayStart.getTime()) / msPerDay)
}

export function isAgreementDeadlineSoon(deadlineValue, thresholdDays = 5) {
  const remaining = getAgreementDaysRemaining(deadlineValue)
  if (remaining === null) {
    return false
  }
  return remaining < thresholdDays
}

export function formatAgreementRemainingLabel(deadlineValue) {
  const remaining = getAgreementDaysRemaining(deadlineValue)
  if (remaining === null) {
    return ''
  }
  if (remaining < 0) {
    return 'просрочено'
  }
  if (remaining === 0) {
    return 'сегодня последний день'
  }
  return `осталось ${pluralizeDays(remaining)}`
}

export function getAgreementDeadlineHint(deadlineValue, periodLabel) {
  if (isAgreementDeadlineSoon(deadlineValue)) {
    return formatAgreementRemainingLabel(deadlineValue)
  }
  return periodLabel
}

/**
 * @typedef {Object} FilterParticipant
 * @property {string} id
 * @property {string} name
 * @property {string} email
 * @property {string} initial
 */

/** @type {FilterParticipant[]} */
export const FILTER_PARTICIPANTS = [
  { id: 'john-bassil', name: 'JohnBassil', email: 'vasil.ev81@mail.ru', initial: 'J' },
  { id: 'alex-ablizin', name: 'Александр Аблизин', email: 'zen@8ber.ru', initial: 'А' },
  { id: 'artem-dmitrenko', name: 'Артём Дмитренко', email: 'darkrogua@inbox.ru', initial: 'А' },
  { id: 'ivan-petrov', name: 'Иван Петров', email: 'ivan.petrov@example.ru', initial: 'И' },
  { id: 'elena-smirnova', name: 'Елена Смирнова', email: 'elena.s@example.ru', initial: 'Е' },
]

/**
 * @param {FilterParticipant} participant
 * @param {'is' | 'is_not'} [match]
 */
export function formatParticipantFilterLabel(participant, match = 'is') {
  const prefix = match === 'is_not' ? 'Участник не' : 'Участник'
  return `${prefix}: ${participant.name}`
}

/**
 * @param {FilterParticipant[]} participants
 * @param {'is' | 'is_not'} [match]
 */
export function formatParticipantsFilterLabel(participants, match = 'is') {
  if (!participants.length) {
    return match === 'is_not' ? 'Участник не выбран' : 'Участник не выбран'
  }
  if (participants.length === 1) {
    return formatParticipantFilterLabel(participants[0], match)
  }
  const prefix = match === 'is_not' ? 'Участники не' : 'Участники'
  return `${prefix}: ${participants.map((item) => item.name).join(', ')}`
}

/**
 * @typedef {Object} FilterStatusOption
 * @property {string} id
 * @property {string} label
 */

/** @type {FilterStatusOption[]} */
export const FILTER_STATUS_OPTIONS = [
  { id: 'in_progress', label: 'В работе' },
  { id: 'planned', label: 'Запланирована' },
  { id: 'draft', label: 'Черновик' },
  { id: 'approved', label: 'Согласована' },
]

/**
 * @param {FilterStatusOption} status
 * @param {'is' | 'is_not'} [match]
 */
export function formatStatusFilterLabel(status, match = 'is') {
  const prefix = match === 'is_not' ? 'Статус не' : 'Статус'
  return `${prefix}: ${status.label}`
}

/** @typedef {'draft' | 'awaiting' | 'approved'} AgreementStatus */

/**
 * @typedef {Object} PersonRef
 * @property {string} name
 * @property {string} [initial]
 * @property {string} [label]
 */

/**
 * @typedef {Object} AgreementItem
 * @property {string} id
 * @property {number} number
 * @property {string} title
 * @property {string} createdAt
 * @property {string} deadline
 * @property {string} daysLabel
 * @property {boolean} isUrgent
 * @property {PersonRef} author
 * @property {PersonRef[]} participants
 * @property {AgreementStatus} status
 * @property {number} voted
 * @property {number} total
 * @property {boolean} isFavorite
 * @property {boolean} isOwner
 */

export const AGREEMENT_BLOCK_TYPES = [
  { id: 'files', label: 'Файлы' },
  { id: 'gallery', label: 'Галерея' },
  { id: 'text', label: 'Текстовый блок' },
  { id: 'checkbox', label: 'Чекбокс' },
]

export const AGREEMENT_EDITOR_BLOCK_TYPES = [
  { id: 'gallery', label: 'Галерея' },
  { id: 'files', label: 'Файлы' },
  { id: 'text', label: 'Текстовый блок' },
  { id: 'checkbox', label: 'Чекбокс' },
]

export const AGREEMENT_EDITOR_INTRO =
  'Наименование. Говорит о том, что вы находитесь в разделе согласования. Здесь вы можете добавлять блоки с контентом: файлы, текст, ссылки и другие элементы.'

/**
 * @typedef {Object} AgreementBlock
 * @property {string} id
 * @property {string} type
 * @property {string} label
 * @property {string} [title]
 * @property {string} [description]
 * @property {string} [content]
 * @property {Array<{ id: string, name: string, mime?: string, previewUrl?: string | null }>} [files]
 * @property {Array<{ id: string, name: string, mime?: string, previewUrl?: string }>} [photos]
 * @property {string} [prompt]
 * @property {Array<{ id: string, label: string, checked?: boolean }>} [items]
 */

/**
 * @typedef {Object} SectionVotingStats
 * @property {number} approved
 * @property {number} rejected
 * @property {number} pending
 */

/**
 * @param {AgreementBlock} block
 */
export function normalizeTextBlock(block) {
  if (!block || block.type !== 'text') {
    return block
  }
  if (block.title === undefined) {
    block.title = ''
  }
  if (block.description === undefined) {
    block.description = ''
  }
  if (block.content === undefined) {
    block.content = ''
  }
  return block
}

/**
 * @param {AgreementBlock} block
 */
export function normalizeFilesBlock(block) {
  if (!block || block.type !== 'files') {
    return block
  }
  if (block.title === undefined) {
    block.title = block.label || 'Файлы'
  }
  if (!Array.isArray(block.files)) {
    block.files = []
  }
  return block
}

/**
 * @param {AgreementBlock} block
 */
export function normalizeGalleryBlock(block) {
  if (!block || block.type !== 'gallery') {
    return block
  }
  if (block.title === undefined) {
    block.title = block.label || 'Галерея'
  }
  if (!Array.isArray(block.photos)) {
    block.photos = []
  }
  return block
}

/**
 * @param {AgreementBlock} block
 */
export function normalizeCheckboxBlock(block) {
  if (!block || block.type !== 'checkbox') {
    return block
  }
  if (block.title === undefined) {
    block.title = block.label || 'Чекбокс'
  }
  if (block.prompt === undefined) {
    block.prompt = ''
  }
  if (!Array.isArray(block.items)) {
    block.items = []
  }
  return block
}

/**
 * @param {AgreementBlock} block
 */
export function normalizeAgreementBlock(block) {
  if (!block) {
    return block
  }
  if (block.type === 'text') {
    return normalizeTextBlock(block)
  }
  if (block.type === 'files') {
    return normalizeFilesBlock(block)
  }
  if (block.type === 'gallery') {
    return normalizeGalleryBlock(block)
  }
  if (block.type === 'checkbox') {
    return normalizeCheckboxBlock(block)
  }
  return block
}

/**
 * @typedef {Object} SectionSettings
 * @property {{ day1: boolean, hours2: boolean, hour1: boolean }} reminders
 * @property {'all' | 'majority'} completionCondition
 * @property {boolean} isImportant
 * @property {boolean} visibilityEnabled
 * @property {boolean} participantsSeeEachOther
 * @property {boolean} showResultsBefore
 * @property {boolean} showResultsAfter
 */

/** @type {SectionSettings} */
export const DEFAULT_SECTION_SETTINGS = {
  reminders: { day1: false, hours2: true, hour1: false },
  completionCondition: 'all',
  isImportant: true,
  visibilityEnabled: true,
  participantsSeeEachOther: true,
  showResultsBefore: false,
  showResultsAfter: true,
}

/**
 * @param {object} section
 */
export function ensureSectionSettings(section) {
  if (!section) {
    return section
  }
  if (!section.settings) {
    section.settings = {
      ...DEFAULT_SECTION_SETTINGS,
      reminders: { ...DEFAULT_SECTION_SETTINGS.reminders },
    }
  } else {
    section.settings = {
      ...DEFAULT_SECTION_SETTINGS,
      ...section.settings,
      reminders: {
        ...DEFAULT_SECTION_SETTINGS.reminders,
        ...(section.settings.reminders || {}),
      },
    }
  }
  if (section.leaderId === undefined) {
    section.leaderId = null
  }
  return section
}

/**
 * @typedef {Object} AgreementSection
 * @property {string} id
 * @property {string} title
 * @property {string[]} participantIds
 * @property {string[]} groupIds
 * @property {string|null} [leaderId]
 * @property {SectionSettings} [settings]
 * @property {AgreementBlock[]} blocks
 * @property {SectionVotingStats} [votingStats]
 * @property {number} [voted]
 * @property {number} [total]
 */

/**
 * @param {string} [title]
 */
export function createAgreementSection(title = 'Новый раздел') {
  return ensureSectionSettings({
    id: `section-${Date.now()}`,
    title: title.trim() || 'Новый раздел',
    participantIds: [],
    groupIds: [],
    leaderId: null,
    blocks: [],
    votingStats: { approved: 0, rejected: 0, pending: 100 },
    voted: 0,
    total: 0,
  })
}

/**
 * @param {object} agreement
 */
export function ensureAgreementSections(agreement) {
  if (!agreement) {
    return agreement
  }
  if (!Array.isArray(agreement.sections) || agreement.sections.length === 0) {
    agreement.sections = [
      createAgreementSection('Раздел 1'),
    ]
    if (Array.isArray(agreement.blocks) && agreement.blocks.length > 0) {
      agreement.sections[0].blocks = [...agreement.blocks]
    }
    delete agreement.blocks
  }
  for (const section of agreement.sections) {
    section.title = normalizeLegacySectionTitle(section.title)
    ensureSectionSettings(section)
    if (!Array.isArray(section.blocks)) {
      section.blocks = []
    }
    for (const block of section.blocks) {
      normalizeAgreementBlock(block)
    }
    if (!section.votingStats) {
      section.votingStats = { approved: 0, rejected: 0, pending: 100 }
    }
  }
  return agreement
}

/**
 * @typedef {Object} CreateAgreementForm
 * @property {string} title
 * @property {string} description
 * @property {string} startDate
 * @property {string} endDate
 */

/**
 * @returns {AgreementBlock[]}
 */
export function createEditorDemoBlocks() {
  const files = createAgreementBlock({ id: 'files', label: 'Файлы' })
  const text = createAgreementBlock({ id: 'text', label: 'Текст' })
  text.title = 'Просмотреть договор и написать свои комментарии'
  text.description =
    'Наименование. Говорит о том, что вы находитесь в разделе согласования. Здесь вы можете добавлять блоки с контентом: файлы, текст, ссылки и другие элементы.'
  const checkbox = createAgreementBlock({ id: 'checkbox', label: 'Чекбокс' })
  checkbox.prompt = 'Кто просмотрел договор ответить на несколько вопросов'
  checkbox.items = [
    { id: 'item-1', label: 'Есть ли вопросы к договору?', checked: false },
    { id: 'item-2', label: 'Готовы ли обсудить их во вторник?', checked: false },
  ]
  return [files, text, checkbox]
}

/**
 * @param {CreateAgreementForm} form
 * @param {number} nextNumber
 */
export function createDraftAgreement(form, nextNumber) {
  const section = createAgreementSection('Раздел 1')
  section.blocks = []
  section.votingStats = { approved: 0, rejected: 0, pending: 100 }
  section.total = 0
  section.voted = 0

  return {
    id: String(nextNumber),
    number: nextNumber,
    title: form.title.trim(),
    description: form.description.trim(),
    createdAt: formatDateRu(),
    startDate: formatIsoDateToRu(form.startDate),
    deadline: formatIsoDateToRu(form.endDate),
    publishDate: null,
    categoryId: null,
    daysLabel: formatAgreementDaysLabel(
      formatIsoDateToRu(form.startDate) || formatDateRu(),
      formatIsoDateToRu(form.endDate)
    ),
    isUrgent: true,
    author: { name: 'Александр Аблизин' },
    participants: [
      { label: '80' },
      { label: 'И' },
      { label: 'Е' },
      { label: 'И' },
      { label: 'Н' },
    ],
    status: 'draft',
    voted: 12,
    total: 15,
    isFavorite: false,
    isOwner: true,
    sections: [section],
  }
}

/**
 * @param {{ id: string, label: string }} blockType
 */
export function createAgreementBlock(blockType) {
  const block = {
    id: `block-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
    type: blockType.id,
    label: blockType.label,
  }
  if (blockType.id === 'text') {
    block.title = ''
    block.description = ''
    block.content = ''
  }
  if (blockType.id === 'files') {
    block.title = blockType.label
    block.files = []
  }
  if (blockType.id === 'gallery') {
    block.title = blockType.label
    block.photos = []
  }
  if (blockType.id === 'checkbox') {
    block.title = blockType.label
    block.prompt = ''
    block.items = []
  }
  if (blockType.id === 'link') {
    block.title = ''
    block.url = ''
  }
  if (blockType.id === 'code') {
    block.title = ''
    block.content = ''
  }
  return block
}

export function getNextAgreementNumber(agreements) {
  const max = agreements.reduce((acc, item) => Math.max(acc, item.number || 0), 0)
  return max + 1
}

export const CURRENT_APPROVER_ID = 'artem-dmitrenko'

/** @type {AgreementItem[]} */
export const MOCK_AGREEMENTS = [
  {
    id: '308',
    number: 308,
    title: 'Азимут тур, ТЗ, Релиз 2. Можно название в две строки, и даже...',
    createdAt: '20.07.2024',
    deadline: '08.08.2024',
    daysLabel: '25 дней',
    isUrgent: true,
    author: { name: 'Александр Аблизин' },
    participants: [
      { label: '80' },
      { label: 'И' },
      { label: 'Е' },
      { label: 'М' },
      { label: 'Н' },
    ],
    status: 'awaiting',
    voted: 10,
    total: 50,
    isFavorite: false,
    isOwner: true,
    description: 'Спецификация интеграции API и схема обмена данными.',
    sections: [
      {
        id: 'section-308',
        title: 'Техническое задание',
        blocks: [
          {
            id: 'block-308-text',
            type: 'text',
            label: 'Текст',
            title: 'Описание релиза',
            content: 'Интеграция платёжного шлюза и личного кабинета партнёра.',
          },
        ],
      },
    ],
  },
  {
    id: '307',
    number: 307,
    title: 'Азимут тур, ТЗ, Релиз 2. Можно название в две строки, и даже...',
    createdAt: '20.07.2024',
    deadline: '08.08.2024',
    daysLabel: '15 дней',
    isUrgent: false,
    author: { name: 'Александр Аблизин' },
    participants: [
      { label: '80' },
      { label: 'И' },
      { label: 'Е' },
      { label: 'М' },
      { label: 'Н' },
    ],
    status: 'draft',
    voted: 10,
    total: 30,
    isFavorite: true,
    isOwner: false,
    description: 'Черновик бюджета на Q3 и план закупок.',
    sections: [
      {
        id: 'section-307',
        title: 'Финансы',
        blocks: [
          {
            id: 'block-307-text',
            type: 'text',
            label: 'Текст',
            title: 'Смета',
            content: 'Распределение бюджета по отделам и подрядчикам.',
          },
        ],
      },
    ],
  },
  {
    id: '306',
    number: 306,
    title: 'Азимут тур, ТЗ, Релиз 2. Можно название в две строки, и даже...',
    createdAt: '20.07.2024',
    deadline: '08.08.2024',
    daysLabel: '15 дней',
    isUrgent: false,
    author: { name: 'Александр Аблизин' },
    participants: [
      { label: '80' },
      { label: 'И' },
      { label: 'Е' },
      { label: 'М' },
      { label: 'Н' },
    ],
    status: 'approved',
    voted: 30,
    total: 30,
    isFavorite: false,
    isOwner: false,
    description: 'Итоговый отчёт по проекту и закрывающие документы.',
    sections: [
      {
        id: 'section-306',
        title: 'Архив',
        blocks: [
          {
            id: 'block-306-text',
            type: 'text',
            label: 'Текст',
            title: 'Заключение',
            content: 'Все этапы релиза завершены, акты подписаны.',
          },
        ],
      },
    ],
  },
  createFullDemoAgreement(),
]

function createDemoSection({ title, isActive, blocks, stats, settings }) {
  const section = createAgreementSection(title)
  section.blocks = blocks
  section.votingStats = stats || { approved: 0, rejected: 0, pending: 100 }
  if (settings) {
    section.settings = { ...section.settings, ...settings }
  }
  if (isActive) {
    section.participantIds = [CURRENT_APPROVER_ID]
  } else {
    section.participantIds = ['elena-vasilyeva', 'maria-gorbunova']
  }
  return section
}

function createFullDemoAgreement() {
  const designSection = createDemoSection({
    title: 'Дизайн',
    isActive: true,
    stats: { approved: 75, rejected: 15, pending: 10 },
    settings: { showResultsBefore: true, showResultsAfter: true },
    blocks: [
      {
        id: 'block-design-text',
        type: 'text',
        label: 'Текстовый блок',
        title: 'По дизайну',
        description: 'Основные требования к визуальной части проекта.',
        content: 'Здесь размещается подробный текст с пояснениями для согласующего. Необходимо проверить соответствие брендбуку, доступность интерфейса и корректность всех макетов.',
      },
      {
        id: 'block-design-gallery',
        type: 'gallery',
        label: 'Галерея',
        title: 'Макеты и эскизы',
        photos: [
          { id: 'photo-1', name: 'Главная страница', previewUrl: 'https://picsum.photos/id/1015/400/300' },
          { id: 'photo-2', name: 'Каталог', previewUrl: 'https://picsum.photos/id/1036/400/300' },
        ],
      },
    ],
  })

  const programmingSection = createDemoSection({
    title: 'Программирование',
    isActive: false,
    stats: { approved: 40, rejected: 10, pending: 50 },
    settings: { showResultsBefore: true, showResultsAfter: true },
    blocks: [
      {
        id: 'block-dev-text',
        type: 'text',
        label: 'Текстовый блок',
        title: 'Техническое задание',
        description: 'Архитектура и требования к разработке.',
        content: 'Раздел для технических специалистов. Здесь описывается стек, интеграции и требования к производительности. Для текущего пользователя этот раздел доступен только для просмотра.',
      },
      {
        id: 'block-dev-files',
        type: 'files',
        label: 'Файлы',
        title: 'Документация',
        files: [
          { id: 'file-1', name: 'API-спецификация.pdf', mime: 'application/pdf', previewUrl: '' },
          { id: 'file-2', name: 'Схема БД.xlsx', mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', previewUrl: '' },
        ],
      },
    ],
  })

  const managementSection = createDemoSection({
    title: 'Менеджмент',
    isActive: false,
    stats: { approved: 20, rejected: 5, pending: 75 },
    settings: { showResultsBefore: true, showResultsAfter: true },
    blocks: [
      {
        id: 'block-mgmt-text',
        type: 'text',
        label: 'Текстовый блок',
        title: 'По менеджменту',
        description: 'Организационные вопросы и сроки.',
        content: 'Раздел для руководителей и менеджеров. Здесь описываются бюджет, сроки, зоны ответственности и ключевые контрольные точки. Для текущего пользователя этот раздел доступен только для просмотра.',
      },
      {
        id: 'block-mgmt-files',
        type: 'files',
        label: 'Файлы',
        title: 'Отчёты и документы',
        files: [
          { id: 'file-3', name: 'План проекта.pdf', mime: 'application/pdf', previewUrl: '' },
          { id: 'file-4', name: 'Бюджет.xlsx', mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', previewUrl: '' },
        ],
      },
      {
        id: 'block-mgmt-gallery',
        type: 'gallery',
        label: 'Галерея',
        title: 'Схемы и диаграммы',
        photos: [
          { id: 'photo-3', name: 'Диаграмма процесса', previewUrl: 'https://picsum.photos/id/1060/400/300' },
          { id: 'photo-4', name: 'Структура команды', previewUrl: 'https://picsum.photos/id/1070/400/300' },
        ],
      },
      {
        id: 'block-mgmt-links',
        type: 'link',
        label: 'Ссылки',
        title: 'Полезные ссылки',
        links: [
          { id: 'link-1', title: 'Брендбук компании', url: 'https://example.com/brandbook', description: 'Официальный гайд по использованию логотипа и цветов.' },
          { id: 'link-2', title: 'Требования к контенту', url: 'https://example.com/content', description: 'Правила оформления текстов и изображений.' },
        ],
      },
    ],
  })

  return {
    id: '305',
    number: 305,
    title: 'Демо-согласование с разделами актив/неактив',
    createdAt: formatDateRu(),
    deadline: '31.12.2026',
    daysLabel: 'До конца года',
    isUrgent: true,
    author: { name: 'Александр Аблизин' },
    participants: [
      { label: 'А' },
      { label: 'И' },
      { label: 'Е' },
      { label: 'М' },
      { label: 'Н' },
      { label: '+3' },
    ],
    status: 'awaiting',
    voted: 0,
    total: 15,
    isFavorite: false,
    isOwner: false,
    description: 'Тестовое согласование для проверки активных и неактивных разделов в режиме согласователя.',
    sections: [designSection, programmingSection, managementSection],
  }
}

export const DEFAULT_FILTER_SECTIONS = [
  {
    id: 'mine',
    tabId: 'agreements',
    tabLabel: 'Согласования',
    title: 'Мои согласования',
    filters: [
      { id: 'urgency-set', label: 'Срочность установлена', category: 'urgency', value: 'set' },
      { id: 'only-mine', label: 'Только мои согласования', category: 'participant', value: 'mine' },
      { id: 'created-7d', label: 'Создано последние 7 дней', category: 'created', value: '7d' },
    ],
  },
  {
    id: 'drafts',
    tabId: 'drafts',
    tabLabel: 'Черновики',
    title: 'Черновики',
    filters: [
      { id: 'draft-mine', label: 'Только мои согласования', category: 'participant', value: 'mine' },
      { id: 'draft-created', label: 'Создано последние 7 дней', category: 'created', value: '7d' },
    ],
  },
  {
    id: 'favorites',
    tabId: 'favorites',
    tabLabel: 'Избранное',
    title: 'Избранное',
    filters: [
      { id: 'fav-urgency', label: 'Срочность установлена', category: 'urgency', value: 'set' },
      { id: 'fav-mine', label: 'Только мои согласования', category: 'participant', value: 'mine' },
      { id: 'fav-created', label: 'Создано последние 7 дней', category: 'created', value: '7d' },
      { id: 'fav-participants', label: 'Участники: Александр Абликин, Иван Петров', category: 'participant', value: 'custom', avatars: ['А', 'И'] },
      { id: 'fav-status', label: 'Статус: В работе', category: 'status', value: 'in_progress' },
    ],
  },
  {
    id: 'archive',
    tabId: 'archive',
    tabLabel: 'Архив',
    title: 'Архив',
    filters: [
      { id: 'arch-urgency', label: 'Срочность установлена', category: 'urgency', value: 'set' },
      { id: 'arch-mine', label: 'Только мои согласования', category: 'participant', value: 'mine' },
      { id: 'arch-created', label: 'Создано последние 7 дней', category: 'created', value: '7d' },
    ],
  },
]

export function buildTopTabsFromSections(sections, agreements = []) {
  return sections.map((section) => {
    const tabId = section.tabId || section.id
    return {
      id: tabId,
      label: section.tabLabel || section.title,
      badge: resolveTopTabBadge(tabId, agreements),
    }
  })
}

function resolveTopTabBadge(tabId, agreements) {
  if (tabId === 'drafts') {
    const count = agreements.filter((item) => item.status === 'draft').length
    return count > 0 ? count : undefined
  }

  if (tabId === 'favorites') {
    const count = agreements.filter((item) => item.isFavorite).length
    return count > 0 ? count : undefined
  }

  const preset = DEFAULT_TOP_TABS.find((tab) => tab.id === tabId)
  return preset?.badge
}

/**
 * @param {object} agreement
 */
function collectAgreementContentParts(agreement) {
  const parts = []

  if (agreement.description) {
    parts.push(agreement.description)
  }

  for (const section of agreement.sections || []) {
    if (section.title) {
      parts.push(section.title)
    }

    for (const block of section.blocks || []) {
      parts.push(block.label, block.title, block.description, block.content, block.prompt)

      for (const file of block.files || []) {
        parts.push(file.name)
      }

      for (const photo of block.photos || []) {
        parts.push(photo.name)
      }

      for (const item of block.items || []) {
        parts.push(item.label)
      }
    }
  }

  return parts.filter(Boolean)
}

/**
 * @param {object} agreement
 */
export function getAgreementTitleHaystack(agreement) {
  return [agreement.title, String(agreement.number), agreement.author?.name]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
}

/**
 * @param {object} agreement
 */
export function getAgreementContentHaystack(agreement) {
  return collectAgreementContentParts(agreement).join(' ').toLowerCase()
}

/**
 * @param {object} agreement
 * @param {string} query
 * @param {SearchScopeId} [searchScope]
 */
export function agreementMatchesSearch(agreement, query, searchScope = 'content') {
  const q = String(query || '').trim().toLowerCase()
  if (!q) {
    return true
  }

  const titleHaystack = getAgreementTitleHaystack(agreement)
  if (searchScope === 'title') {
    return titleHaystack.includes(q)
  }

  const contentHaystack = getAgreementContentHaystack(agreement)
  return `${titleHaystack} ${contentHaystack}`.includes(q)
}

export function filterAgreements(agreements, tabId, query, searchScope = 'content') {
  let list = [...agreements]

  if (tabId === 'drafts') {
    list = list.filter((item) => item.status === 'draft')
  } else if (tabId === 'agreements') {
    list = list.filter((item) => item.status !== 'draft')
  } else if (tabId === 'favorites') {
    list = list.filter((item) => item.isFavorite)
  } else if (tabId === 'archive') {
    list = list.filter((item) => item.status === 'approved' || item.status === 'archived')
  } else if (tabId === 'work') {
    list = list.filter((item) => item.status === 'awaiting')
  } else if (String(tabId).startsWith('custom-')) {
    list = list.filter((item) => item.status !== 'draft')
  }

  const q = String(query || '').trim().toLowerCase()
  if (q) {
    list = list.filter((item) => agreementMatchesSearch(item, q, searchScope))
  }

  return list
}

export function sortAgreements(list, sortId) {
  const sorted = [...list]

  if (sortId === 'favorites') {
    return sorted.sort((a, b) => Number(b.isFavorite) - Number(a.isFavorite))
  }

  if (sortId === 'newest') {
    return sorted.sort((a, b) => b.number - a.number)
  }

  if (sortId === 'activity') {
    return sorted.sort((a, b) => b.voted / b.total - a.voted / a.total)
  }

  return sorted.sort((a, b) => a.number - b.number)
}
