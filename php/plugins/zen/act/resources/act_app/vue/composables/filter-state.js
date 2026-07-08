export function defaultFilters() {
  return {
    owners: {
      enabled: false,
      users: [],
    },
    created: {
      enabled: false,
      from: '',
      to: '',
    },
    sortCreated: {
      enabled: false,
      order: 'desc',
    },
    tags: {
      enabled: false,
      items: [],
      ops: [],
    },
  }
}

export function normalizeUser(user) {
  return {
    login: String(user?.login || ''),
    display_name: String(user?.display_name || user?.login || 'User'),
  }
}

export function cloneFilters(filters) {
  const base = defaultFilters()
  if (!filters) {
    return base
  }

  base.owners.enabled = Boolean(filters.owners?.enabled)
  base.owners.users = Array.isArray(filters.owners?.users)
    ? filters.owners.users.map(normalizeUser).filter((u) => u.login)
    : []

  base.created.enabled = Boolean(filters.created?.enabled)
  base.created.from = typeof filters.created?.from === 'string' ? filters.created.from : ''
  base.created.to = typeof filters.created?.to === 'string' ? filters.created.to : ''

  base.sortCreated.enabled = Boolean(filters.sortCreated?.enabled)
  base.sortCreated.order = filters.sortCreated?.order === 'asc' ? 'asc' : 'desc'

  base.tags.enabled = Boolean(filters.tags?.enabled)
  base.tags.items = Array.isArray(filters.tags?.items)
    ? filters.tags.items.map((tag) => String(tag)).filter(Boolean)
    : []
  base.tags.ops = Array.isArray(filters.tags?.ops)
    ? filters.tags.ops.map((op) => (op === 'and' ? 'and' : 'or'))
    : []

  return base
}

function usersEqual(a, b) {
  if (a.length !== b.length) {
    return false
  }
  return a.every((user, index) => {
    const other = b[index]
    return user.login === other.login && user.display_name === other.display_name
  })
}

export function filtersEqual(a, b) {
  const left = cloneFilters(a)
  const right = cloneFilters(b)

  if (left.owners.enabled !== right.owners.enabled) {
    return false
  }
  if (!usersEqual(left.owners.users, right.owners.users)) {
    return false
  }

  if (left.created.enabled !== right.created.enabled) {
    return false
  }
  if (left.created.from !== right.created.from || left.created.to !== right.created.to) {
    return false
  }

  if (left.sortCreated.enabled !== right.sortCreated.enabled) {
    return false
  }
  if (left.sortCreated.order !== right.sortCreated.order) {
    return false
  }

  if (left.tags.enabled !== right.tags.enabled) {
    return false
  }
  if (left.tags.items.join('|') !== right.tags.items.join('|')) {
    return false
  }
  if (left.tags.ops.join('|') !== right.tags.ops.join('|')) {
    return false
  }

  return true
}

export function applyFilters(target, snapshot) {
  const next = cloneFilters(snapshot)
  target.owners.enabled = next.owners.enabled
  target.owners.users = next.owners.users
  target.created.enabled = next.created.enabled
  target.created.from = next.created.from
  target.created.to = next.created.to
  target.sortCreated.enabled = next.sortCreated.enabled
  target.sortCreated.order = next.sortCreated.order
  target.tags.enabled = next.tags.enabled
  target.tags.items = next.tags.items
  target.tags.ops = next.tags.ops
}

export function parseStoredFilters(raw) {
  if (!raw) {
    return defaultFilters()
  }

  try {
    const parsed = JSON.parse(raw)
    return cloneFilters(parsed)
  } catch {
    return defaultFilters()
  }
}
