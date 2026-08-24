<template>
  <div class="concord-profile-hero">
    <button
      type="button"
      class="concord-profile-hero__avatar-btn"
      :aria-label="avatarAriaLabel"
      @click="$emit('avatar-click')"
    >
      <img
        v-if="avatarUrl"
        :src="avatarUrl"
        alt=""
        class="concord-profile-hero__avatar concord-profile-hero__avatar--image"
      >
      <span v-else class="concord-profile-hero__avatar" aria-hidden="true">{{ avatarInitial }}</span>
    </button>
    <div class="concord-profile-hero__text">
      <p class="concord-profile-hero__name">{{ name }}</p>
      <p v-if="handle" class="concord-profile-hero__handle">@{{ handle }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConcordProfileHero',
  props: {
    name: {
      type: String,
      required: true,
    },
    handle: {
      type: String,
      default: '',
    },
    avatarUrl: {
      type: String,
      default: '',
    },
    avatarInitial: {
      type: String,
      default: '',
    },
    avatarAriaLabel: {
      type: String,
      default: 'Изменить фото профиля',
    },
  },
  emits: ['avatar-click'],
}
</script>
