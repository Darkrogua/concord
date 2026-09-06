<template>
  <label class="concord-agreement-form__field">
    <span class="concord-agreement-form__label">{{ label }}</span>
    <div class="concord-auth-password">
      <input
        :value="modelValue"
        class="concord-agreement-form__input"
        :type="visible ? 'text' : 'password'"
        :name="name"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :required="required"
        @input="$emit('update:modelValue', $event.target.value)"
      >
      <button
        type="button"
        class="concord-auth-password__toggle"
        :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'"
        @click="visible = !visible"
      >
        <svg v-if="visible" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/>
          <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.6"/>
        </svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4M6.7 6.7C4.6 8.1 3 10 2 12c1 2 3.5 6 10 6 1.8 0 3.3-.4 4.6-1M17 9.3C18.2 10.4 19.2 11.7 20 13c-1 2-3.5 6-10 6-.8 0-1.5-.1-2.2-.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
  </label>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  name: { type: String, default: 'password' },
  placeholder: { type: String, default: '' },
  autocomplete: { type: String, default: 'current-password' },
  required: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const visible = ref(false)
</script>
