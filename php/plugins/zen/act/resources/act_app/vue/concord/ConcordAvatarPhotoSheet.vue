<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-menu-sheet concord-avatar-photo-sheet" role="dialog" aria-label="Фото профиля">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <button type="button" class="concord-menu-sheet__item" @click="$emit('take-photo')">
        Сделать фото
      </button>
      <button type="button" class="concord-menu-sheet__item" @click="$emit('choose-gallery')">
        Выбрать из галереи
      </button>
      <button
        v-if="hasAvatar"
        type="button"
        class="concord-menu-sheet__item concord-menu-sheet__item--destructive"
        @click="$emit('remove-photo')"
      >
        Удалить фото
      </button>
      <button type="button" class="concord-menu-sheet__item" @click="$emit('close')">
        Отмена
      </button>
    </div>
  </template>
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
