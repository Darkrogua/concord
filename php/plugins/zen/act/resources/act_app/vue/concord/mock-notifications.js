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
 * @property {string} [time]
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
