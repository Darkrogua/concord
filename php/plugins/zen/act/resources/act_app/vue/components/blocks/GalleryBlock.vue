<template>
  <section v-if="mode === 'site'" class="tg-site-gallery tg-gallery">
    <header v-if="block.name" class="tg-gallery__header">
      <h2 class="tg-gallery__title">{{ block.name }}</h2>
    </header>

    <GalleryView
      :act-id="actId"
      :block-id="block.id"
      :items="activeItems"
    />
  </section>

  <article v-else :class="['tg-block-card', 'tg-gallery', { 'tg-block-card--editing': isEdit }]">
    <header v-if="viewer === 'owner'" class="tg-block-card__header">
      <BlockTitleEdit
        :name="block.name"
        type-label="Фотогалерея"
        fallback="Фотогалерея"
        :editable="viewer === 'owner'"
        @save-name="saveName"
      />
      <span v-if="isEdit && dirty" class="tg-block-card__status">Есть изменения</span>
    </header>
    <header v-else-if="block.name" class="tg-gallery__header">
      <h2 class="tg-gallery__title">{{ block.name }}</h2>
    </header>

    <template v-if="isEdit">
      <div
        class="tg-gallery-editor__dropzone"
        :class="{ 'tg-gallery-editor__dropzone--active': dragActive }"
        @dragenter.prevent="dragActive = true"
        @dragover.prevent
        @dragleave.prevent="dragActive = false"
        @drop.prevent="onDrop"
      >
        <p class="tg-gallery-editor__dropzone-text">
          Перетащите изображения сюда или
          <label class="tg-gallery-editor__file-label">
            выберите файлы
            <input
              ref="fileInput"
              type="file"
              class="tg-gallery-editor__file-input"
              accept="image/jpeg,image/png,image/gif,image/webp,image/avif"
              multiple
              :disabled="saving || uploading"
              @change="onFileSelect"
            >
          </label>
        </p>
        <p v-if="uploading" class="tg-field__hint">Загрузка…</p>
      </div>

      <div ref="slidesRef" class="tg-gallery-editor__slides">
        <div
          v-for="item in items"
          :key="item.id"
          class="tg-gallery-editor__slide"
          :data-image-id="item.id"
        >
          <div class="tg-gallery-editor__preview">
            <img
              :src="imageUrl(item.id)"
              :alt="item.alt || item.filename"
              class="tg-gallery-editor__thumb"
            >
          </div>
          <div class="tg-gallery-editor__meta">
            <div class="tg-gallery-editor__filename">{{ item.filename || item.id }}</div>
            <div class="tg-gallery-editor__stats">
              <span>{{ formatFileSizeMb(item.size_bytes) }}</span>
              <span>{{ formatImageDimensions(item.width, item.height) }}</span>
            </div>
            <label class="tg-gallery-editor__alt">
              <span class="tg-field__label">Alt</span>
              <input
                v-model="item.alt"
                class="tg-input"
                type="text"
                :disabled="saving"
                placeholder="Описание (необязательно)"
              >
            </label>
            <label class="tg-gallery-editor__active">
              <input
                v-model="item.active"
                type="checkbox"
                :disabled="saving"
              >
              <span>Активно</span>
            </label>
          </div>
          <button
            type="button"
            class="tg-btn tg-btn--ghost tg-btn--icon"
            aria-label="Удалить изображение"
            :disabled="saving || uploading"
            @click="removeItem(item)"
          >
            ×
          </button>
        </div>
      </div>

      <p v-if="items.length === 0" class="tg-field__hint">Нет изображений. Загрузите хотя бы одно.</p>
      <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>

      <div class="tg-block-actions tg-block-actions--compact">
        <button class="tg-btn" type="button" :disabled="saving || uploading || !dirty" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </button>
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving || uploading" @click="cancelEdit">
          Готово
        </button>
        <button class="tg-btn tg-btn--danger" type="button" :disabled="saving || uploading" @click="remove">
          Удалить
        </button>
      </div>
    </template>

    <template v-else>
      <div class="tg-gallery-admin-summary">
        <p v-if="allItems.length === 0" class="tg-block-content tg-block-content--empty">
          Нет изображений
        </p>
        <template v-else>
          <p class="tg-gallery-admin-summary__meta">
            {{ summaryText }}
          </p>
          <ul class="tg-gallery-admin-summary__thumbs" aria-label="Миниатюры галереи">
            <li
              v-for="item in allItems"
              :key="item.id"
              class="tg-gallery-admin-summary__thumb-item"
              :class="{ 'tg-gallery-admin-summary__thumb-item--inactive': !item.active }"
            >
              <img
                :src="imageUrl(item.id)"
                :alt="item.alt || item.filename"
                class="tg-gallery-admin-summary__thumb"
                loading="lazy"
              >
            </li>
          </ul>
        </template>
      </div>
      <div v-if="viewer === 'owner'" class="tg-block-actions tg-block-actions--compact">
        <BlockSettingsButton @click="$emit('settings', block)" />
        <BlockEditButton @click="startEdit" />
      </div>
    </template>
  </article>
</template>

<script>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Sortable from 'sortablejs'
import { uploadActAsset } from '../../../../common/js/act-api.js'
import {
  formatFileSizeMb,
  formatImageDimensions,
  galleryImageUrl,
} from '../../composables/useGalleryImage.js'
import BlockEditButton from './BlockEditButton.vue'
import BlockSettingsButton from './BlockSettingsButton.vue'
import BlockTitleEdit from './BlockTitleEdit.vue'

const GalleryView = defineAsyncComponent(() => import('./GalleryView.vue'))

function cloneItems(source) {
  return (Array.isArray(source) ? source : []).map((item, index) => ({
    id: item.id,
    filename: item.filename || '',
    mime: item.mime || '',
    size_bytes: item.size_bytes || 0,
    width: item.width ?? null,
    height: item.height ?? null,
    alt: item.alt || '',
    active: item.active !== false,
    sort_order: typeof item.sort_order === 'number' ? item.sort_order : index,
  }))
}

function itemsEqual(a, b) {
  return JSON.stringify(a) === JSON.stringify(b)
}

export default {
  name: 'GalleryBlock',
  components: {
    BlockEditButton,
    BlockSettingsButton,
    BlockTitleEdit,
    GalleryView,
  },
  props: {
    actId: {
      type: String,
      required: true,
    },
    block: {
      type: Object,
      required: true,
    },
    viewer: {
      type: String,
      default: 'guest',
      validator: (value) => ['owner', 'guest'].includes(value),
    },
    mode: {
      type: String,
      default: 'edit',
      validator: (value) => ['edit', 'site'].includes(value),
    },
    startInEditMode: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save', 'delete', 'settings'],
  setup(props, { emit }) {
    const fileInput = ref(null)
    const slidesRef = ref(null)
    const items = ref(cloneItems(props.block?.data?.items))
    const saving = ref(false)
    const uploading = ref(false)
    const error = ref('')
    const editing = ref(props.startInEditMode)
    const dragActive = ref(false)
    let sortable = null

    const originalItems = computed(() => cloneItems(props.block?.data?.items))
    const dirty = computed(() => !itemsEqual(items.value, originalItems.value))
    const isEdit = computed(() => props.viewer === 'owner' && editing.value)

    const activeItems = computed(() => (
      originalItems.value
        .filter((item) => item.active !== false)
        .sort((a, b) => (a.sort_order - b.sort_order) || a.id.localeCompare(b.id))
    ))

    const allItems = computed(() => (
      [...originalItems.value].sort((a, b) => (a.sort_order - b.sort_order) || a.id.localeCompare(b.id))
    ))

    const summaryText = computed(() => {
      const total = allItems.value.length
      const active = activeItems.value.length
      const totalWord = total === 1 ? 'изображение' : total < 5 ? 'изображения' : 'изображений'
      const activeWord = active === 1 ? 'активное' : 'активных'

      return `${total} ${totalWord} · ${active} ${activeWord}`
    })

    const imageUrl = (imageId) => galleryImageUrl(props.actId, props.block.id, imageId)

    const applySortOrder = () => {
      items.value = items.value.map((item, index) => ({
        ...item,
        sort_order: index,
      }))
    }

    const initSortable = () => {
      if (!slidesRef.value || sortable) {
        return
      }

      sortable = Sortable.create(slidesRef.value, {
        animation: 150,
        handle: '.tg-gallery-editor__preview',
        draggable: '.tg-gallery-editor__slide',
        onEnd: () => {
          const ordered = []
          slidesRef.value.querySelectorAll('.tg-gallery-editor__slide').forEach((el, index) => {
            const id = el.getAttribute('data-image-id')
            const found = items.value.find((item) => item.id === id)
            if (found) {
              ordered.push({ ...found, sort_order: index })
            }
          })
          if (ordered.length === items.value.length) {
            items.value = ordered
          }
        },
      })
    }

    const destroySortable = () => {
      if (sortable) {
        sortable.destroy()
        sortable = null
      }
    }

    watch(
      () => props.block,
      () => {
        items.value = cloneItems(props.block?.data?.items)
        error.value = ''
        if (!props.startInEditMode) {
          editing.value = false
        }
      },
      { immediate: true, deep: true }
    )

    watch(
      () => props.startInEditMode,
      (value) => {
        if (value) {
          editing.value = true
        }
      }
    )

    watch(isEdit, async (value) => {
      if (value) {
        await nextTick()
        initSortable()
      } else {
        destroySortable()
      }
    })

    onMounted(() => {
      if (isEdit.value) {
        initSortable()
      }
    })

    onBeforeUnmount(() => {
      destroySortable()
    })

    const uploadFiles = (fileList) => {
      const files = Array.from(fileList || []).filter((file) => file && file.type?.startsWith('image/'))
      if (files.length === 0) {
        return
      }

      uploading.value = true
      error.value = ''

      const uploadNext = (index) => {
        if (index >= files.length) {
          uploading.value = false
          applySortOrder()
          return
        }

        uploadActAsset({
          act_id: props.actId,
          block_id: props.block.id,
          file: files[index],
          then: (res) => {
            if (res?.ok && res.data?.item) {
              const item = res.data.item
              items.value.push({
                id: item.id,
                filename: item.filename || files[index].name,
                mime: item.mime || files[index].type,
                size_bytes: item.size_bytes || files[index].size,
                width: item.width ?? null,
                height: item.height ?? null,
                alt: '',
                active: true,
                sort_order: items.value.length,
              })
              uploadNext(index + 1)
              return
            }
            uploading.value = false
            error.value = res?.errors?.[0]?.message || 'Не удалось загрузить изображение'
          },
          catch: () => {
            uploading.value = false
            error.value = 'Ошибка сети при загрузке'
          },
        })
      }

      uploadNext(0)
    }

    const onFileSelect = (event) => {
      uploadFiles(event.target.files)
      if (fileInput.value) {
        fileInput.value.value = ''
      }
    }

    const onDrop = (event) => {
      dragActive.value = false
      uploadFiles(event.dataTransfer?.files)
    }

    const removeItem = (item) => {
      items.value = items.value.filter((entry) => entry.id !== item.id)
      applySortOrder()
    }

    const startEdit = () => {
      editing.value = true
    }

    const cancelEdit = () => {
      items.value = cloneItems(originalItems.value)
      error.value = ''
      editing.value = false
    }

    const save = () => {
      if (!dirty.value || saving.value) {
        return
      }

      saving.value = true
      error.value = ''
      emit('save', {
        block: props.block,
        data: {
          ...(props.block.data || {}),
          type: 'gallery',
          items: items.value.map((item, index) => ({
            id: item.id,
            filename: item.filename,
            mime: item.mime,
            size_bytes: item.size_bytes,
            width: item.width,
            height: item.height,
            alt: item.alt || '',
            active: item.active !== false,
            sort_order: index,
          })),
        },
        done: () => {
          saving.value = false
          editing.value = false
        },
        fail: (message) => {
          saving.value = false
          error.value = message || 'Не удалось сохранить блок'
        },
      })
    }

    const remove = () => {
      if (saving.value) {
        return
      }
      emit('delete', props.block)
    }

    const saveName = ({ name, done, fail }) => {
      emit('save', {
        block: props.block,
        data: props.block.data || { type: 'gallery', items: originalItems.value },
        name,
        done,
        fail,
      })
    }

    return {
      fileInput,
      slidesRef,
      items,
      saving,
      uploading,
      error,
      dirty,
      isEdit,
      activeItems,
      allItems,
      summaryText,
      dragActive,
      imageUrl,
      formatFileSizeMb,
      formatImageDimensions,
      onFileSelect,
      onDrop,
      removeItem,
      startEdit,
      cancelEdit,
      save,
      remove,
      saveName,
    }
  },
}
</script>
