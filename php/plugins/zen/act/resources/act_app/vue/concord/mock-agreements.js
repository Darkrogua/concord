export const DEFAULT_TOP_TABS = [
  { id: 'agreements', label: 'Согласования' },
  { id: 'favorites', label: 'Избранное' },
  { id: 'archive', label: 'Архив' },
]

export const SORT_OPTIONS = [
  { id: 'oldest', label: 'Дате' },
  { id: 'newest', label: 'Сначала новые' },
  { id: 'activity', label: 'По активности' },
  { id: 'deadline', label: 'По сроку' },
]

/** @typedef {'awaiting' | 'overdue' | 'approved'} AgreementStatus */

/**
 * @typedef {Object} AgreementSection
 * @property {string} id
 * @property {string} name
 * @property {number} voted
 * @property {number} total
 */

/**
 * @typedef {Object} AgreementItem
 * @property {string} id
 * @property {string} title
 * @property {string} date
 * @property {AgreementStatus} status
 * @property {boolean} isFavorite
 * @property {boolean} isOwner
 * @property {AgreementSection[]} sections
 */

/** @type {AgreementItem[]} */
export const MOCK_AGREEMENTS = [
  {
    id: '1',
    title: 'Договор № 1256 — Разработка сайта для магазина',
    date: '10.05.2024',
    status: 'awaiting',
    isFavorite: false,
    isOwner: false,
    sections: [
      { id: 's1', name: 'Дизайн', voted: 3, total: 3 },
      { id: 's2', name: 'Программирование', voted: 5, total: 11 },
      { id: 's3', name: 'Маркетинг', voted: 0, total: 8 },
    ],
  },
  {
    id: '2',
    title: 'Договор № 1256 — Разработка сайта для магазина',
    date: '10.05.2024',
    status: 'overdue',
    isFavorite: true,
    isOwner: false,
    sections: [
      { id: 's1', name: 'Дизайн', voted: 1, total: 3 },
      { id: 's2', name: 'Программирование', voted: 0, total: 5 },
    ],
  },
  {
    id: '3',
    title: 'Договор № 1256 — Разработка сайта для магазина',
    date: '10.05.2024',
    status: 'approved',
    isFavorite: false,
    isOwner: true,
    sections: [
      { id: 's1', name: 'Дизайн', voted: 4, total: 4 },
      { id: 's2', name: 'Программирование', voted: 8, total: 8 },
      { id: 's3', name: 'Маркетинг', voted: 2, total: 2 },
    ],
  },
]

export function filterAgreements(agreements, tabId, query) {
  let list = [...agreements]

  if (tabId === 'favorites') {
    list = list.filter((item) => item.isFavorite)
  } else if (tabId === 'archive') {
    list = []
  }

  const q = String(query || '').trim().toLowerCase()
  if (q) {
    list = list.filter((item) => item.title.toLowerCase().includes(q))
  }

  return list
}
