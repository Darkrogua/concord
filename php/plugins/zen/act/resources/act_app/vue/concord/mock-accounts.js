/** @typedef {{ id: string, name: string, initial: string, avatarUrl: string }} ConcordAccount */

/** @type {ConcordAccount[]} */
export const DEFAULT_ACCOUNTS = [
  { id: '1', name: 'Александр Аблизин', initial: 'А', avatarUrl: '' },
  { id: '2', name: 'McMraak', initial: 'M', avatarUrl: '' },
  { id: '3', name: 'Alex', initial: 'A', avatarUrl: '' },
]

export function getAccountById(id, accounts = DEFAULT_ACCOUNTS) {
  return accounts.find((account) => account.id === id) || accounts[0]
}

export function formatAccountNavLabel(name = '') {
  const trimmed = String(name).trim()
  if (!trimmed) return 'Профиль'
  const [firstWord] = trimmed.split(/\s+/)
  return firstWord || trimmed
}
