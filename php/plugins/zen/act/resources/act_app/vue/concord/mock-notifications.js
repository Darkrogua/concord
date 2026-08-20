export const DEFAULT_PROFILE = {
  login: 'alex-ablizin',
  firstName: 'Александр',
  lastName: 'Аблизин',
  email: '',
  phone: '+78888952222',
  birthDate: '',
  timezone: '',
  avatarInitial: 'А',
  avatarUrl: '',
}

export const DEFAULT_NOTIFICATION_SETTINGS = {
  generalChats: true,
  personalChats: false,
  groups: false,
}

export { DEFAULT_PROFILE_GROUPS } from './mock-groups.js'

/**
 * @typedef {Object} NotificationItem
 * @property {string} id
 * @property {boolean} [empty]
 * @property {boolean} [isRead]
 * @property {string} [avatarInitial]
 * @property {string} [avatarUrl]
 * @property {string} [title]
 * @property {string} [body]
 * @property {string} [linkText]
 * @property {number} [agreementNumber]
 * @property {string} [agreementId]
 * @property {string} [time]
 */

/**
 * @typedef {Object} NotificationSection
 * @property {string} dateLabel
 * @property {NotificationItem[]} items
 */

/** @type {NotificationSection[]} */
const ALEXANDER_NOTIFICATION_SECTIONS = [
  {
    dateLabel: 'Сегодня',
    items: [
      {
        id: 'n-today-1',
        isRead: false,
        avatarInitial: 'А',
        title: 'Система',
        body: 'Вы получили новый голос по',
        linkText: 'Согласованию #308',
        agreementNumber: 308,
        agreementId: '308',
        time: '10:24',
      },
      {
        id: 'n-today-2',
        isRead: false,
        avatarInitial: 'М',
        title: 'Мария К.',
        body: 'Прокомментировала раздел в',
        linkText: 'Согласовании #305',
        agreementNumber: 305,
        agreementId: '305',
        time: '14:08',
      },
    ],
  },
  {
    dateLabel: '14.04.25',
    items: [
      {
        id: 'n-140425-1',
        isRead: true,
        avatarInitial: 'А',
        title: 'Система',
        body: 'Согласование завершено:',
        linkText: 'Согласование #308',
        agreementNumber: 308,
        agreementId: '308',
        time: '18:42',
      },
    ],
  },
]

/** @type {NotificationSection[]} */
const MCMRAAK_NOTIFICATION_SECTIONS = [
  {
    dateLabel: 'Сегодня',
    items: [
      {
        id: 'n-m-1',
        isRead: false,
        avatarInitial: 'А',
        title: 'Система',
        body: 'Новый комментарий в',
        linkText: 'Согласовании #307',
        agreementNumber: 307,
        agreementId: '307',
        time: '09:15',
      },
    ],
  },
]

/** @type {NotificationSection[]} */
const ALEX_ACCOUNT_NOTIFICATION_SECTIONS = [
  {
    dateLabel: 'Сегодня',
    items: [
      {
        id: 'n-a-1',
        isRead: false,
        avatarInitial: 'И',
        title: 'Иван Петров',
        body: 'Проголосовал в',
        linkText: 'Согласовании #306',
        agreementNumber: 306,
        agreementId: '306',
        time: '11:40',
      },
    ],
  },
]

/** @type {Record<string, NotificationSection[]>} */
export const MOCK_NOTIFICATION_SECTIONS_BY_ACCOUNT = {
  1: ALEXANDER_NOTIFICATION_SECTIONS,
  2: MCMRAAK_NOTIFICATION_SECTIONS,
  3: ALEX_ACCOUNT_NOTIFICATION_SECTIONS,
}

/** @type {NotificationSection[]} */
export const MOCK_NOTIFICATION_SECTIONS = ALEXANDER_NOTIFICATION_SECTIONS

export function cloneNotificationSections(sections = []) {
  return sections.map((section) => ({
    ...section,
    items: section.items.map((item) => ({ ...item })),
  }))
}

export function cloneNotificationSectionsByAccount(source = MOCK_NOTIFICATION_SECTIONS_BY_ACCOUNT) {
  return Object.fromEntries(
    Object.entries(source).map(([accountId, sections]) => [
      accountId,
      cloneNotificationSections(sections),
    ])
  )
}

export function countUnreadNotifications(sections = []) {
  let count = 0
  for (const section of sections) {
    for (const item of section.items) {
      if (!item.empty && !item.isRead) {
        count += 1
      }
    }
  }
  return count
}

/** Demo mapping: contact id → account that receives in-app events for that person. */
export const PARTICIPANT_ACCOUNT_IDS = {
  'alex-ablizin': '1',
  'artem-dmitrenko': '2',
  'elena-vasilyeva': '3',
  'maria-gorbunova': '2',
  'roman-gorbachev': '3',
  'sergey-gordienko': '2',
  'ivan-petrov': '3',
  'elena-smirnova': '2',
}

function formatNotificationTime(date = new Date()) {
  return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

/**
 * @param {Record<string, NotificationSection[]>} sectionsByAccount
 * @param {{ agreement: object, participantIds: string[] }} payload
 */
export function pushVoteResetNotifications(sectionsByAccount, { agreement, participantIds }) {
  if (!agreement || !participantIds?.length || !sectionsByAccount) {
    return
  }

  const linkText = `Согласованию #${agreement.number}`
  const time = formatNotificationTime()

  for (const participantId of participantIds) {
    const accountId = PARTICIPANT_ACCOUNT_IDS[participantId]
    if (!accountId) {
      continue
    }

    const sections = sectionsByAccount[accountId]
    if (!sections) {
      continue
    }

    let todaySection = sections.find((section) => section.dateLabel === 'Сегодня')
    if (!todaySection) {
      todaySection = { dateLabel: 'Сегодня', items: [] }
      sections.unshift(todaySection)
    }

    todaySection.items.unshift({
      id: `vote-reset-${Date.now()}-${participantId}-${Math.random().toString(36).slice(2, 6)}`,
      isRead: false,
      avatarInitial: 'А',
      title: 'Система',
      body: 'Ваш голос по согласованию был обнулён. Необходимо согласовать заново:',
      linkText,
      agreementNumber: agreement.number,
      agreementId: agreement.id,
      time,
    })
  }
}
