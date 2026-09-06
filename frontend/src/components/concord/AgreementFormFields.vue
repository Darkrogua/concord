<template>
  <div class="concord-agreement-form">
    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Основное</h2>
      <div class="concord-profile-card">
        <div class="concord-agreement-form__card-body">
          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Название</span>
            <input
              :value="model.title"
              class="concord-agreement-form__input"
              type="text"
              placeholder="Введите название согласования"
              required
              @input="update('title', $event.target.value)"
            >
          </label>

          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Описание</span>
            <textarea
              :value="model.description"
              class="concord-agreement-form__input concord-agreement-form__textarea"
              rows="4"
              placeholder="Кратко опишите, что нужно согласовать"
              @input="update('description', $event.target.value)"
            />
          </label>
        </div>
      </div>
    </section>

    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Сроки</h2>
      <div class="concord-profile-card">
        <div class="concord-agreement-form__card-body">
          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Дедлайн</span>
            <input
              :value="deadlineLocal"
              class="concord-agreement-form__input"
              type="datetime-local"
              @input="updateDeadline($event.target.value)"
            >
          </label>
          <p class="concord-agreement-form__hint">
            Участники должны проголосовать до указанного времени.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  model: { type: Object, required: true },
})

const emit = defineEmits(['update:model'])

function pad(value) {
  return String(value).padStart(2, '0')
}

function toDatetimeLocal(value) {
  if (!value) return ''
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const deadlineLocal = computed(() => toDatetimeLocal(props.model.deadline))

function update(field, value) {
  emit('update:model', { ...props.model, [field]: value })
}

function updateDeadline(value) {
  emit('update:model', {
    ...props.model,
    deadline: value ? new Date(value) : null,
  })
}
</script>
