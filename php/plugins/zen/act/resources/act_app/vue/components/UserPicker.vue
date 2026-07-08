<template>
  <div class="tg-user-picker">
    <div class="tg-user-picker__chips">
      <button
        v-if="showAllChip && modelValue.length === 0"
        type="button"
        class="tg-chip tg-chip--active"
        :disabled="disabled"
      >
        Все
      </button>
      <button
        v-for="user in modelValue"
        :key="user.login"
        type="button"
        class="tg-chip"
        :disabled="disabled"
        @click="removeUser(user)"
      >
        <span>{{ user.display_name }}</span>
        <span class="tg-chip__login">@{{ user.login }}</span>
        <span aria-hidden="true">×</span>
      </button>
    </div>
    <input
      :id="inputId"
      v-model="query"
      class="tg-input tg-user-picker__input"
      type="text"
      :disabled="disabled"
      placeholder="Найти по логину или имени…"
      autocomplete="off"
      @keydown.enter.prevent="selectFirst"
    >
    <div v-if="dropdownOpen" class="tg-user-picker__dropdown">
      <button
        v-for="user in filteredOptions"
        :key="user.login"
        type="button"
        class="tg-user-picker__option"
        :disabled="disabled"
        @click="addUser(user)"
      >
        <span class="tg-user-picker__avatar">{{ initials(user) }}</span>
        <span class="tg-user-picker__identity">
          <span class="tg-user-picker__name">{{ user.display_name }}</span>
          <span class="tg-user-picker__login">@{{ user.login }}</span>
        </span>
      </button>
      <div v-if="searching" class="tg-user-picker__empty">Поиск…</div>
      <div v-else-if="filteredOptions.length === 0" class="tg-user-picker__empty">Пользователи не найдены</div>
    </div>
  </div>
</template>

<script>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAppState } from '../app-state'

const normalizeUser = (user) => ({
  login: String(user?.login || ''),
  display_name: String(user?.display_name || user?.login || 'User'),
})

export default {
  name: 'UserPicker',
  props: {
    modelValue: {
      type: Array,
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    inputId: {
      type: String,
      default: '',
    },
    showAllChip: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const { api } = useAppState()
    const query = ref('')
    const options = ref([])
    const searching = ref(false)
    let searchTimer = null

    const selectedLogins = computed(() => new Set(props.modelValue.map((user) => user.login)))
    const filteredOptions = computed(() => options.value.filter((user) => !selectedLogins.value.has(user.login)))
    const dropdownOpen = computed(() => query.value.trim() !== '')

    const searchUsers = () => {
      const value = query.value.trim()
      if (searchTimer) {
        window.clearTimeout(searchTimer)
      }
      if (!value) {
        options.value = []
        searching.value = false
        return
      }

      searching.value = true
      searchTimer = window.setTimeout(() => {
        api({
          api: `Auth:searchUsers?q=${encodeURIComponent(value)}&limit=10`,
          then: (res) => {
            searching.value = false
            options.value = res?.ok && Array.isArray(res.data?.users)
              ? res.data.users.map(normalizeUser).filter((user) => user.login !== '')
              : []
          },
          catch: () => {
            searching.value = false
            options.value = []
          },
        })
      }, 250)
    }

    const addUser = (user) => {
      const normalized = normalizeUser(user)
      if (!normalized.login || selectedLogins.value.has(normalized.login)) {
        return
      }
      emit('update:modelValue', [...props.modelValue, normalized])
      query.value = ''
      options.value = []
    }

    const removeUser = (user) => {
      emit('update:modelValue', props.modelValue.filter((item) => item.login !== user.login))
    }

    const selectFirst = () => {
      if (filteredOptions.value.length > 0) {
        addUser(filteredOptions.value[0])
      }
    }

    const initials = (user) => {
      const name = (user.display_name || user.login || '').trim()
      return name.slice(0, 2).toUpperCase()
    }

    watch(query, searchUsers)

    onBeforeUnmount(() => {
      if (searchTimer) {
        window.clearTimeout(searchTimer)
      }
    })

    return {
      query,
      searching,
      filteredOptions,
      dropdownOpen,
      addUser,
      removeUser,
      selectFirst,
      initials,
    }
  },
}
</script>
