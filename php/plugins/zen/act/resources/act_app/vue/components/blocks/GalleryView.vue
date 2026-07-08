<template>
  <div v-if="items.length === 1" class="tg-gallery-single">
    <img
      :src="imageUrl(items[0].id)"
      :alt="items[0].alt || ''"
      class="tg-gallery-single__image"
      loading="lazy"
    >
  </div>

  <GallerySwiper
    v-else-if="items.length > 1"
    :act-id="actId"
    :block-id="blockId"
    :items="items"
  />
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { galleryImageUrl } from '../../composables/useGalleryImage.js'

const GallerySwiper = defineAsyncComponent(() => import('./GallerySwiper.vue'))

export default {
  name: 'GalleryView',
  components: { GallerySwiper },
  props: {
    actId: {
      type: String,
      required: true,
    },
    blockId: {
      type: String,
      required: true,
    },
    items: {
      type: Array,
      default: () => [],
    },
  },
  setup(props) {
    const imageUrl = (imageId) => galleryImageUrl(props.actId, props.blockId, imageId)

    return { imageUrl }
  },
}
</script>
