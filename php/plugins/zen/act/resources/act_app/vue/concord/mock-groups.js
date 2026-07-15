/**
 * @typedef {Object} GroupContact
 * @property {string} id
 * @property {string} shortName
 * @property {string} name
 * @property {string} initial
 * @property {string} [email]
 */

/**
 * @typedef {Object} ProfileGroup
 * @property {string} id
 * @property {string} title
 * @property {number} memberCount
 * @property {string[]} memberIds
 */

/** @type {GroupContact[]} */
export const MOCK_CONTACTS = [
  { id: 'alex-ablizin', shortName: 'А. Аблизин', name: 'Александр Аблизин', initial: 'А', email: 'zen@8ber.ru' },
  { id: 'artem-dmitrenko', shortName: 'А. Дмитренко', name: 'Артём Дмитренко', initial: 'А', email: 'darkrogua@inbox.ru' },
  { id: 'elena-vasilyeva', shortName: 'Е. Васильева', name: 'Елена Васильева', initial: 'Е', email: 'elena.v@example.ru' },
  { id: 'maria-gorbunova', shortName: 'М. Горбунова', name: 'Мария Горбунова', initial: 'М', email: 'maria.g@example.ru' },
  { id: 'roman-gorbachev', shortName: 'Р. Горбачёв', name: 'Роман Горбачёв', initial: 'Р', email: 'roman.g@example.ru' },
  { id: 'sergey-gordienko', shortName: 'С. Гордиенко', name: 'Сергей Гордиенко', initial: 'С', email: 'sergey.g@example.ru' },
  { id: 'ivan-petrov', shortName: 'И. Петров', name: 'Иван Петров', initial: 'И', email: 'ivan.petrov@example.ru' },
  { id: 'elena-smirnova', shortName: 'Е. Смирнова', name: 'Елена Смирнова', initial: 'Е', email: 'elena.s@example.ru' },
]

const DEV_MEMBER_IDS = [
  'alex-ablizin',
  'artem-dmitrenko',
  'elena-vasilyeva',
  'maria-gorbunova',
  'roman-gorbachev',
  'sergey-gordienko',
  'ivan-petrov',
  'elena-smirnova',
]

/** @type {ProfileGroup[]} */
export const DEFAULT_PROFILE_GROUPS = [
  { id: 'dev', title: 'Программисты', memberIds: [...DEV_MEMBER_IDS], memberCount: 12 },
  { id: 'design', title: 'Дизайнеры', memberIds: ['elena-vasilyeva', 'maria-gorbunova'], memberCount: 12 },
  { id: 'admin', title: 'Администрация', memberIds: ['alex-ablizin', 'roman-gorbachev'], memberCount: 12 },
]

export function cloneProfileGroups(groups = DEFAULT_PROFILE_GROUPS) {
  return groups.map((group) => ({
    ...group,
    memberIds: [...group.memberIds],
  }))
}

export function syncGroupMemberCount(group) {
  return {
    ...group,
    memberCount: group.memberIds.length,
  }
}

/**
 * @param {string} title
 * @param {string[]} memberIds
 */
export function createProfileGroup(title, memberIds = []) {
  return syncGroupMemberCount({
    id: `group-${Date.now()}`,
    title: title.trim(),
    memberIds: [...memberIds],
    memberCount: memberIds.length,
  })
}

/**
 * @param {ProfileGroup} group
 * @param {GroupContact[]} contacts
 */
export function getContactsForGroup(group, contacts = MOCK_CONTACTS) {
  const ids = new Set(group.memberIds)
  return contacts.filter((contact) => ids.has(contact.id))
}

/**
 * @param {GroupContact[]} contacts
 * @param {string} query
 */
export function filterContacts(contacts, query = '') {
  const normalized = query.trim().toLowerCase()
  if (!normalized) {
    return contacts
  }
  return contacts.filter(
    (contact) =>
      contact.shortName.toLowerCase().includes(normalized) ||
      contact.name.toLowerCase().includes(normalized) ||
      (contact.email && contact.email.toLowerCase().includes(normalized))
  )
}
