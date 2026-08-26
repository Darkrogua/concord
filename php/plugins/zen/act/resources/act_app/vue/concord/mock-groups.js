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
  { id: 'anna-kuznetsova', shortName: 'А. Кузнецова', name: 'Анна Кузнецова', initial: 'А', email: 'anna.k@example.ru' },
  { id: 'dmitry-orlov', shortName: 'Д. Орлов', name: 'Дмитрий Орлов', initial: 'Д', email: 'dmitry.o@example.ru' },
  { id: 'olga-novikova', shortName: 'О. Новикова', name: 'Ольга Новикова', initial: 'О', email: 'olga.n@example.ru' },
  { id: 'pavel-sokolov', shortName: 'П. Соколов', name: 'Павел Соколов', initial: 'П', email: 'pavel.s@example.ru' },
  { id: 'natalya-fedorova', shortName: 'Н. Фёдорова', name: 'Наталья Фёдорова', initial: 'Н', email: 'natalya.f@example.ru' },
  { id: 'andrey-volkov', shortName: 'А. Волков', name: 'Андрей Волков', initial: 'А', email: 'andrey.v@example.ru' },
  { id: 'kristina-lebedeva', shortName: 'К. Лебедева', name: 'Кристина Лебедева', initial: 'К', email: 'kristina.l@example.ru' },
  { id: 'mikhail-popov', shortName: 'М. Попов', name: 'Михаил Попов', initial: 'М', email: 'mikhail.p@example.ru' },
  { id: 'yulia-kozlova', shortName: 'Ю. Козлова', name: 'Юлия Козлова', initial: 'Ю', email: 'yulia.k@example.ru' },
  { id: 'viktor-egorov', shortName: 'В. Егоров', name: 'Виктор Егоров', initial: 'В', email: 'viktor.e@example.ru' },
  { id: 'tatyana-morozova', shortName: 'Т. Морозова', name: 'Татьяна Морозова', initial: 'Т', email: 'tatyana.m@example.ru' },
  { id: 'denis-shevchenko', shortName: 'Д. Шевченко', name: 'Денис Шевченко', initial: 'Д', email: 'denis.sh@example.ru' },
  { id: 'konstantin-belov', shortName: 'К. Белов', name: 'Константин Белов', initial: 'К', email: 'konstantin.b@example.ru' },
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
  { id: 'dev', title: 'Программисты', memberIds: [...DEV_MEMBER_IDS], memberCount: DEV_MEMBER_IDS.length },
  { id: 'design', title: 'Дизайнеры', memberIds: ['elena-vasilyeva', 'maria-gorbunova'], memberCount: 2 },
  { id: 'admin', title: 'Администрация', memberIds: ['alex-ablizin', 'roman-gorbachev'], memberCount: 2 },
]

export function cloneProfileGroups(groups = DEFAULT_PROFILE_GROUPS) {
  return groups.map((group) => ({
    ...group,
    memberIds: Array.isArray(group?.memberIds) ? [...group.memberIds] : [],
    memberCount: Array.isArray(group?.memberIds)
      ? group.memberIds.length
      : Number(group?.memberCount) || 0,
  }))
}

export function syncGroupMemberCount(group) {
  const memberIds = Array.isArray(group?.memberIds) ? group.memberIds : []
  return {
    ...group,
    memberIds: [...memberIds],
    memberCount: memberIds.length,
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
 * Unique voter IDs for a section: direct participants + members of selected groups.
 * @param {object} section
 * @param {ProfileGroup[]} groups
 * @returns {string[]}
 */
export function collectSectionVoterIds(section, groups = []) {
  if (!section) {
    return []
  }

  const seen = new Set()

  for (const contactId of section.participantIds || []) {
    if (contactId) {
      seen.add(contactId)
    }
  }

  for (const groupId of section.groupIds || []) {
    const group = groups.find((item) => item.id === groupId)
    if (!group) {
      continue
    }
    for (const memberId of group.memberIds || []) {
      if (memberId) {
        seen.add(memberId)
      }
    }
  }

  return [...seen]
}

/**
 * @param {object} section
 * @param {ProfileGroup[]} groups
 * @param {GroupContact[]} contacts
 */
export function resolveSectionParticipants(section, groups = [], contacts = MOCK_CONTACTS) {
  if (!section) {
    return []
  }

  const contactsById = new Map(contacts.map((contact) => [contact.id, contact]))

  return collectSectionVoterIds(section, groups).map((participantId) => {
    const contact = contactsById.get(participantId)
    return {
      id: participantId,
      label: contact?.initial || contact?.shortName?.charAt(0) || String(participantId).charAt(0).toUpperCase() || '?',
      shortName: contact?.shortName || '',
      name: contact?.name || contact?.shortName || participantId,
      initial: contact?.initial || String(participantId).charAt(0).toUpperCase() || '?',
    }
  })
}

export function sectionHasConfiguredParticipants(section) {
  return Boolean(section?.participantIds?.length || section?.groupIds?.length)
}

/**
 * @param {object} section
 * @param {ProfileGroup[]} groups
 * @param {GroupContact[]} [contacts]
 */
export function resolveSectionVotersCount(section, groups = [], contacts = MOCK_CONTACTS) {
  void contacts
  return collectSectionVoterIds(section, groups).length
}

/**
 * Member IDs that belong to any of the selected section groups.
 * @param {string[]} groupIds
 * @param {ProfileGroup[]} groups
 */
export function collectSelectedGroupMemberIds(groupIds = [], groups = []) {
  const seen = new Set()
  for (const groupId of groupIds || []) {
    const group = groups.find((item) => item.id === groupId)
    for (const memberId of group?.memberIds || []) {
      if (memberId) {
        seen.add(memberId)
      }
    }
  }
  return seen
}

/**
 * Actual members in a group (unique IDs), not a stale cached memberCount.
 * @param {ProfileGroup | null | undefined} group
 */
export function getGroupMembersCount(group) {
  if (!group) {
    return 0
  }
  if (Array.isArray(group.memberIds)) {
    return new Set(group.memberIds.filter(Boolean)).size
  }
  return Number(group.memberCount) || 0
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
