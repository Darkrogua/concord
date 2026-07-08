import { ref } from 'vue'
import { cloneFilters, filtersEqual } from './filter-state.js'
import { isValidPresetColor } from './filter-preset-colors.js'

const STORAGE_KEY = 'act_list_filter_presets'
const MAX_PRESETS = 20

function createPresetId() {
  const cryptoApi = globalThis.crypto
  if (cryptoApi?.randomUUID) {
    return cryptoApi.randomUUID()
  }
  if (cryptoApi?.getRandomValues) {
    const bytes = new Uint8Array(16)
    cryptoApi.getRandomValues(bytes)
    bytes[6] = (bytes[6] & 0x0f) | 0x40
    bytes[8] = (bytes[8] & 0x3f) | 0x80
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
  }
  return `preset-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
}

function loadPresets() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return []
    }
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) {
      return []
    }
    return parsed
      .map((item) => ({
        id: String(item?.id || ''),
        name: String(item?.name || '').trim(),
        color: isValidPresetColor(item?.color) ? item.color : 'blue',
        filters: cloneFilters(item?.filters),
        created_at: String(item?.created_at || ''),
      }))
      .filter((item) => item.id && item.name)
  } catch {
    return []
  }
}

function persistPresets(presets) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(presets))
}

export function useActsFilterPresets() {
  const presets = ref(loadPresets())
  const activePresetId = ref(null)

  const createPreset = (name, color, filters) => {
    const trimmedName = String(name || '').trim()
    if (!trimmedName) {
      throw new Error('Введите название пресета')
    }
    if (trimmedName.length > 40) {
      throw new Error('Название не длиннее 40 символов')
    }
    if (!isValidPresetColor(color)) {
      throw new Error('Выберите цвет')
    }
    if (presets.value.length >= MAX_PRESETS) {
      throw new Error(`Не более ${MAX_PRESETS} пресетов`)
    }
    const duplicate = presets.value.find(
      (preset) => preset.name.toLowerCase() === trimmedName.toLowerCase()
    )
    if (duplicate) {
      throw new Error('Пресет с таким названием уже есть')
    }

    const preset = {
      id: createPresetId(),
      name: trimmedName,
      color,
      filters: cloneFilters(filters),
      created_at: new Date().toISOString(),
    }
    presets.value = [...presets.value, preset]
    persistPresets(presets.value)
    return preset
  }

  const findMatchingPresetId = (filters) => {
    const match = presets.value.find((preset) => filtersEqual(preset.filters, filters))
    return match?.id || null
  }

  const syncActiveFromFilters = (filters) => {
    const matchId = findMatchingPresetId(filters)
    activePresetId.value = matchId
    return matchId
  }

  const applyPreset = (id, replaceFilters) => {
    const preset = presets.value.find((item) => item.id === id)
    if (!preset) {
      return false
    }
    replaceFilters(preset.filters)
    activePresetId.value = id
    return true
  }

  const togglePreset = (id, { replaceFilters, resetAll, onReload }) => {
    if (activePresetId.value === id) {
      resetAll()
      activePresetId.value = null
      onReload?.()
      return
    }

    if (applyPreset(id, replaceFilters)) {
      onReload?.()
    }
  }

  const getMatchingPresetId = (filters) => findMatchingPresetId(filters)

  const deletePreset = (id) => {
    const exists = presets.value.some((item) => item.id === id)
    if (!exists) {
      return false
    }

    presets.value = presets.value.filter((item) => item.id !== id)
    persistPresets(presets.value)

    if (activePresetId.value === id) {
      activePresetId.value = null
    }

    return true
  }

  return {
    presets,
    activePresetId,
    createPreset,
    applyPreset,
    togglePreset,
    syncActiveFromFilters,
    getMatchingPresetId,
    deletePreset,
  }
}
