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

/** @typedef {{ id: string, title: string }} ProfileGroup */

/** @type {ProfileGroup[]} */
export const DEFAULT_PROFILE_GROUPS = [
  { id: 'dev', title: 'Программисты' },
  { id: 'design', title: 'Дизайнеры' },
  { id: 'admin', title: 'Администрация' },
]
