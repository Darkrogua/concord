<template>
  <BottomNav v-if="showTabbar" :active="activeTab" />
</template>

<script>
import BottomNav from './BottomNav.vue'

export default {
  name: 'AppShell',
  components: { BottomNav },
  props: {
    activeTab: {
      type: String,
      default: 'acts',
    },
    showTabbar: {
      type: Boolean,
      default: true,
    },
  },
}
</script>
