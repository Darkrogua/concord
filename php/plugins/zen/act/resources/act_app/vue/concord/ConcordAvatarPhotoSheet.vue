<template>
  <div
    v-if="open"
    class="concord-profile-field-menu concord-avatar-photo-menu"
    role="dialog"
    aria-label="Фото профиля"
    @click.self="$emit('close')"
  >
    <div class="concord-profile-field-menu__sheet" @click.stop>
      <div class="concord-sheet__handle" aria-hidden="true" />
      <button type="button" class="concord-menu-sheet__item" @click.stop="$emit('take-photo')">
        Сделать фото
      </button>
      <button type="button" class="concord-menu-sheet__item" @click.stop="$emit('choose-gallery')">
        Выбрать из галереи
      </button>
      <button
        v-if="hasAvatar"
        type="button"
        class="concord-menu-sheet__item concord-menu-sheet__item--destructive"
        @click.stop="$emit('remove-photo')"
      >
        Удалить фото
      </button>
      <button type="button" class="concord-menu-sheet__item" @click.stop="$emit('close')">
        Отмена
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConcordAvatarPhotoSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    hasAvatar: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'take-photo', 'choose-gallery', 'remove-photo'],
}
</script>
