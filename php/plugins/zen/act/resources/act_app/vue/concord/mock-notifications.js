export const DEFAULT_PROFILE = {
  firstName: 'Александр',
  lastName: 'Аблизин',
  phone: '+78888952222',
  birthDate: '',
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
 */

/**
 * @typedef {Object} NotificationSection
 * @property {string} dateLabel
 * @property {NotificationItem[]} items
 */

/** @type {NotificationSection[]} */
export const MOCK_NOTIFICATION_SECTIONS = [
  {
    dateLabel: 'Сегодня',
    items: [
      {
        id: 'n-today-1',
        isRead: false,
        avatarInitial: 'А',
        title: 'Новое уведомление по аккаунту',
        body: 'Вы получили новый голос',
        linkText: 'Согласование # 308',
        agreementNumber: 308,
        agreementId: '308',
      },
    ],
  },
  {
    dateLabel: 'Вчера',
    items: [
      { id: 'n-yesterday-empty', empty: true },
    ],
  },
  {
    dateLabel: '14.04.25',
    items: [
      {
        id: 'n-140425-1',
        isRead: true,
        avatarInitial: 'А',
        title: 'Новое уведомление по аккаунту',
        body: 'Вы получили новый голос по',
        linkText: 'Согласованию # 308',
        agreementNumber: 308,
        agreementId: '308',
      },
    ],
  },
]
