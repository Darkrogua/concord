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
  { id: 'text', label: 'Текст' },
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
 * @typedef {Object} AgreementSection
 * @property {string} id
 * @property {string} title
 * @property {string[]} participantIds
 * @property {string[]} groupIds
 * @property {AgreementBlock[]} blocks
 * @property {SectionVotingStats} [votingStats]
 * @property {number} [voted]
 * @property {number} [total]
 */

/**
 * @param {string} [title]
 */
export function createAgreementSection(title = 'Новый раздел') {
  return {
    id: `section-${Date.now()}`,
    title: title.trim() || 'Новый раздел',
    participantIds: [],
    groupIds: [],
    blocks: [],
    votingStats: { approved: 0, rejected: 0, pending: 100 },
    voted: 0,
    total: 0,
  }
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
      createAgreementSection(agreement.title || 'Раздел 1'),
    ]
    if (Array.isArray(agreement.blocks) && agreement.blocks.length > 0) {
      agreement.sections[0].blocks = [...agreement.blocks]
    }
    delete agreement.blocks
  }
  for (const section of agreement.sections) {
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
  const section = createAgreementSection(form.title.trim() || 'Раздел 1')
  section.blocks = createEditorDemoBlocks()
  section.votingStats = { approved: 75, rejected: 15, pending: 10 }
  section.total = 15
  section.voted = 12

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
    daysLabel: '25 дней',
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
  return block
}

export function getNextAgreementNumber(agreements) {
  const max = agreements.reduce((acc, item) => Math.max(acc, item.number || 0), 0)
  return max + 1
}

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
  },
]

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

export function filterAgreements(agreements, tabId, query) {
  let list = [...agreements]

  if (tabId === 'drafts') {
    list = list.filter((item) => item.status === 'draft')
  } else if (tabId === 'agreements') {
    list = list.filter((item) => item.status !== 'draft')
  } else if (tabId === 'favorites') {
    list = list.filter((item) => item.isFavorite)
  } else if (tabId === 'archive') {
    list = list.filter((item) => item.status === 'approved')
  } else if (tabId === 'work') {
    list = list.filter((item) => item.status === 'awaiting')
  } else if (String(tabId).startsWith('custom-')) {
    list = list.filter((item) => item.status !== 'draft')
  }

  const q = String(query || '').trim().toLowerCase()
  if (q) {
    list = list.filter(
      (item) =>
        item.title.toLowerCase().includes(q) ||
        String(item.number).includes(q) ||
        item.author.name.toLowerCase().includes(q)
    )
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
