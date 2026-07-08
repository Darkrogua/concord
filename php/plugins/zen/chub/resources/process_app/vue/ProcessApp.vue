<template>
    <div class="process-app">
        <div class="process-controls">
            <button
                class="backend-toolbar-button control-button"
                type="button"
                @click="createNewProcess"
            >
                <i class="icon-list-add"></i>
                <span class="button-label">Добавить процесс</span>
            </button>
        </div>
        <div class="process-list">
            <div v-if="!flow.length" class="process-list__empty text-muted">
                Нет данных
            </div>
            <div
                v-for="(process, index) in flow"
                :key="process.code || index"
                class="process-row"
            >
                <div class="process-row__cell process-row__index">
                    <div class="process-row__label">#</div>
                    <div class="process-row__value">{{ index + 1 }}</div>
                </div>
                <div class="process-row__cell process-row__move">
                    <button
                        class="process-row__move-button"
                        type="button"
                        :disabled="index === 0"
                        @click="moveProcess(process.code, 'up')"
                    >
                        <i class="icon-angle-up"></i>
                    </button>
                    <button
                        class="process-row__move-button"
                        type="button"
                        :disabled="index === flow.length - 1"
                        @click="moveProcess(process.code, 'down')"
                    >
                        <i class="icon-angle-down"></i>
                    </button>
                </div>
                <div class="process-row__cell">
                    <div class="process-row__label">Название</div>
                    <div class="process-row__text">{{ process.name }}</div>
                </div>
                <div class="process-row__cell">
                    <div class="process-row__label">Код</div>
                    <div class="process-row__text">{{ process.code }}</div>
                </div>
                <div class="process-row__cell">
                    <div class="process-row__label">Обработчик</div>
                    <div class="process-row__text">{{ process.handler_path || process.call }}</div>
                </div>
                <div class="process-row__spacer" aria-hidden="true"></div>
                <div class="process-row__actions">
                    <div
                        v-if="getProgress(process.code)"
                        class="process-row__progress"
                    >
                        <div class="process-row__progress__bar">
                            <span
                                class="process-row__progress__fill"
                                :style="{ width: getProgress(process.code).percent + '%' }"
                            ></span>
                        </div>
                        <div class="process-row__progress__text">
                            {{ getProgress(process.code).processed }} из {{ getProgress(process.code).total }}
                        </div>
                    </div>
                    <button
                        class="process-row__action"
                        type="button"
                        :title="getActionTitle(process.code)"
                        :disabled="isActionPending(process.code) || hasError(process.code)"
                        @click="runProcess(process.code)"
                    >
                        <i :class="getActionIcon(process.code)"></i>
                    </button>
                    <span
                        class="process-row__status"
                        :class="getStatusClass(process.code, process.active)"
                        aria-hidden="true"
                    ></span>
                    <button
                        class="process-row__gear"
                        type="button"
                        title="Редактировать"
                        @click="openRecord(process, index)"
                    >
                        <i class="icon-cog"></i>
                    </button>
                </div>
            </div>
        </div>
        <Modal
            :show="record !== null"
            heading="Редактирование процесса"
            maxWidth="1000px"
            @close="closeRecord"
        >
            <div class="process-modal">
                <div
                    v-if="record && getErrorText(record.code)"
                    class="process-modal__error"
                >
                    <div class="process-modal__error__title">Ошибка процесса</div>
                    <div class="process-modal__error__text">
                        {{ getErrorText(record.code) }}
                    </div>
                    <button
                        class="btn btn-warning"
                        type="button"
                        @click="runProcess(record.code)"
                    >
                        Сбросить состояние
                    </button>
                </div>
                <div v-if="record" class="process-modal__editor">
                    <label class="process-modal__switch">
                        <span class="process-modal__label">Активен</span>
                        <span class="process-modal__switch-control">
                            <input
                                v-model="record.active"
                                type="checkbox"
                                class="process-modal__switch-input"
                            >
                            <span class="process-modal__switch-slider" aria-hidden="true"></span>
                        </span>
                    </label>
                    <div class="process-modal__field">
                        <label class="process-modal__label">Название</label>
                        <input
                            v-model="record.name"
                            type="text"
                            class="form-control"
                            placeholder="Название процесса"
                        >
                    </div>
                    <div class="process-modal__field">
                        <label class="process-modal__label">Код</label>
                        <input
                            v-model="record.code"
                            type="text"
                            class="form-control"
                            placeholder="Уникальный code процесса"
                        >
                    </div>
                    <div class="process-modal__field">
                        <label class="process-modal__label">Описание</label>
                        <div
                            class="process-modal__rich"
                            :class="{ 'process-modal__rich--empty': isDescriptionEffectivelyEmpty }"
                        >
                            <div class="process-modal__rich-toolbar">
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionBoldActive() }"
                                    title="Жирный"
                                    @click="toggleDescriptionBold"
                                >
                                    <i class="icon-bold"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionItalicActive() }"
                                    title="Курсив"
                                    @click="toggleDescriptionItalic"
                                >
                                    <i class="icon-italic"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionHeadingActive(1) }"
                                    title="Заголовок 1"
                                    @click="toggleDescriptionHeading(1)"
                                >
                                    <i class="icon-header"></i>
                                    <span class="process-modal__tool-level">1</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionHeadingActive(2) }"
                                    title="Заголовок 2"
                                    @click="toggleDescriptionHeading(2)"
                                >
                                    <i class="icon-header"></i>
                                    <span class="process-modal__tool-level">2</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionBulletListActive() }"
                                    title="Маркированный список"
                                    @click="toggleDescriptionBulletList"
                                >
                                    <i class="icon-list-ul"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    :class="{ 'is-active': isDescriptionOrderedListActive() }"
                                    title="Нумерованный список"
                                    @click="toggleDescriptionOrderedList"
                                >
                                    <i class="icon-list-ol"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    title="Добавить ссылку"
                                    @click="setDescriptionLink"
                                >
                                    <i class="icon-link"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    title="Убрать ссылку"
                                    @click="unsetDescriptionLink"
                                >
                                    <i class="icon-unlink"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    title="Очистить форматирование"
                                    @click="clearDescriptionFormatting"
                                >
                                    <i class="icon-eraser"></i>
                                </button>
                            </div>
                            <EditorContent
                                v-if="description_editor"
                                :editor="description_editor"
                                class="process-modal__rich-content"
                            />
                        </div>
                    </div>

                    <div class="process-modal__calls">
                        <div class="process-modal__calls-header">
                            <div class="process-modal__label">Вызовы и хуки</div>
                            <div class="process-modal__calls-actions">
                                <select v-model="selected_call_field" class="form-control">
                                    <option value="">Выбери поле вызова...</option>
                                    <option
                                        v-for="call_option in availableCallOptions"
                                        :key="call_option.field"
                                        :value="call_option.field"
                                    >
                                        {{ call_option.label }}
                                    </option>
                                </select>
                                <button
                                    class="btn btn-secondary"
                                    type="button"
                                    :disabled="!selected_call_field"
                                    @click="addCallField"
                                >
                                    Добавить вызов
                                </button>
                            </div>
                        </div>

                        <div v-if="!activeCallItems.length" class="process-modal__calls-empty text-muted">
                            Пока не добавлено ни одного вызова
                        </div>

                        <div class="process-modal__call-list">
                            <div
                                v-for="call_item in activeCallItems"
                                :key="call_item.field"
                                class="process-modal__call-card"
                            >
                                <div class="process-modal__call-card-header">
                                    <div class="process-modal__call-card-title">{{ call_item.label }}</div>
                                    <button
                                        class="process-modal__remove"
                                        type="button"
                                        title="Удалить вызов"
                                        @click="removeCallField(call_item.field)"
                                    >
                                        <i class="icon-trash"></i>
                                    </button>
                                </div>
                                <input
                                    v-model="record[call_item.field]"
                                    type="text"
                                    class="form-control"
                                    :placeholder="call_item.placeholder"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <button
                    v-if="record"
                    class="btn btn-warning"
                    type="button"
                    @click="killProcess(record.code)"
                >
                    Убить процесс
                </button>
                <button
                    v-if="record"
                    class="btn btn-secondary"
                    type="button"
                    @click="clearProcess(record.code)"
                >
                    Очистить состояние
                </button>
                <button class="btn btn-danger" type="button" @click="deleteRecord">
                    <i class="icon-trash"></i>
                    Удалить
                </button>
                <button class="btn btn-primary" type="button" @click="saveRecord">
                    Сохранить
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
/*
В момент монтирования считывается идентификатор Flow `meta[name="flow-id"]`
это рендерится в фрагменте `plugins/zen/chub/controllers/flows/flow_app.php`
*/

import { chubApi } from '../../common/js/chub-api'
import Modal from '../../components/modal.vue'
import { Editor as TiptapEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

export default {
    name: 'ProcessApp',
    components: {
        Modal,
        EditorContent
    },
    data() {
        return {
            flow: [],
            flow_id: null,
            flow_states: [],
            flow_states_timer: null,
            pending_actions: {},
            record: null,
            record_index: null,
            record_description: '',
            description_editor: null,
            api: chubApi(),
            selected_call_field: '',
            record_visible_call_fields: [],
            call_field_options: [
                { field: 'handler_path', label: 'Обработчик - Метод', placeholder: 'Vendor.Class.method' },
                { field: 'handler_path_source', label: 'Обработчик - Источник (Источник пакетов)', placeholder: 'Vendor.Class.sourceMethod' },
                { field: 'handler_path_target', label: 'Обработчик - Цель', placeholder: 'Vendor.Class.targetMethod' },
                { field: 'handler_start_path', label: 'Стартовый хук - Метод', placeholder: 'Vendor.Class.startMethod' },
                { field: 'handler_start_path_source', label: 'Стартовый хук - Источник', placeholder: 'Vendor.Class.startSource' },
                { field: 'handler_start_path_target', label: 'Стартовый хук - Цель', placeholder: 'Vendor.Class.startTarget' },
                { field: 'handler_finish_path', label: 'Финишный хук - Метод', placeholder: 'Vendor.Class.finishMethod' },
                { field: 'handler_finish_path_source', label: 'Финишный хук - Источник', placeholder: 'Vendor.Class.finishSource' },
                { field: 'handler_finish_path_target', label: 'Финишный хук - Цель', placeholder: 'Vendor.Class.finishTarget' },
                { field: 'handler_error_path', label: 'Обработчик ошибок - Метод', placeholder: 'Vendor.Class.errorMethod' },
                { field: 'handler_error_path_source', label: 'Обработчик ошибок - Источник', placeholder: 'Vendor.Class.errorSource' },
                { field: 'handler_error_path_target', label: 'Обработчик ошибок - Цель', placeholder: 'Vendor.Class.errorTarget' },
                { field: 'handler_stop_path', label: 'Обработчик остановки - Метод', placeholder: 'Vendor.Class.stopMethod' },
                { field: 'handler_stop_path_source', label: 'Обработчик остановки - Источник', placeholder: 'Vendor.Class.stopSource' },
                { field: 'handler_stop_path_target', label: 'Обработчик остановки - Цель', placeholder: 'Vendor.Class.stopTarget' },
            ]
        }
    },
    mounted() {
        const meta = document.querySelector('meta[name="flow-id"]')
        const flow_id = meta ? meta.getAttribute('content') : null
        if (flow_id) {
            this.flow_id = flow_id
            this.getFlow()
            this.getFlowStates()
            this.flow_states_timer = setInterval(() => {
                this.getFlowStates()
            }, 2000)
        }
    },
    beforeUnmount() {
        if (this.flow_states_timer) {
            clearInterval(this.flow_states_timer)
            this.flow_states_timer = null
        }
        this.destroyDescriptionEditor()
    },
    watch: {
        flow: {
            deep: true,
            handler() {
                this.syncProcessInput()
            }
        }
    },
    computed: {
        activeCallItems() {
            return this.call_field_options.filter(call_option => {
                return this.record_visible_call_fields.includes(call_option.field)
            })
        },
        availableCallOptions() {
            return this.call_field_options.filter(call_option => {
                return !this.record_visible_call_fields.includes(call_option.field)
            })
        },
        isDescriptionEffectivelyEmpty() {
            return this.normalizeDescription(this.record_description) === null
        }
    },
    methods: {
        getFlowState(code) {
            return this.flow_states.find(item => item.code === code) || null
        },
        getProgress(code) {
            const state = this.getFlowState(code)
            const total = Number(state?.batches_total || 0)
            const processed = Number(state?.batches_processed || 0)
            if (!total || total <= 0) {
                return null
            }
            const percent = Math.min(100, Math.round((processed / total) * 100))
            return {
                total,
                processed,
                percent
            }
        },
        isActionPending(code) {
            return !!this.pending_actions[code]
        },
        getErrorText(code) {
            const state = this.getFlowState(code)
            return state?.error || null
        },
        hasError(code) {
            return !!this.getErrorText(code)
        },
        isInProcess(code) {
            const state = this.getFlowState(code)
            return !!state?.in_process
        },
        isCompleted(code) {
            const state = this.getFlowState(code)
            return !!state?.completed
        },
        getActionIcon(code) {
            if (this.hasError(code)) {
                return 'icon-exclamation-triangle'
            }
            if (this.isCompleted(code)) {
                return 'icon-refresh'
            }
            return this.isInProcess(code) ? 'icon-pause' : 'icon-play'
        },
        getActionTitle(code) {
            if (this.hasError(code)) {
                return this.getErrorText(code)
            }
            if (this.isCompleted(code)) {
                return 'Перезапуск'
            }
            return this.isInProcess(code) ? 'Пауза' : 'Запуск'
        },
        getStatusClass(code, isActive) {
            if (this.hasError(code)) {
                return 'process-row__status--error'
            }
            return isActive ? 'process-row__status--on' : 'process-row__status--off'
        },
        normalizeRecord(process) {
            const call = process.call || null
            return {
                active: false,
                name: '',
                code: '',
                description: null,
                handler_path: call,
                handler_path_source: null,
                handler_path_target: null,
                handler_start_path: null,
                handler_start_path_source: null,
                handler_start_path_target: null,
                handler_finish_path: null,
                handler_finish_path_source: null,
                handler_finish_path_target: null,
                handler_error_path: null,
                handler_error_path_source: null,
                handler_error_path_target: null,
                handler_stop_path: null,
                handler_stop_path_source: null,
                handler_stop_path_target: null,
                ...process
            }
        },
        openRecord(process, index) {
            this.record = this.normalizeRecord(process)
            this.record.description = this.normalizeDescription(this.record.description)
            this.record_description = this.record.description || ''
            this.createDescriptionEditor(this.record_description)
            this.record_index = index
            this.selected_call_field = ''
            this.record_visible_call_fields = this.call_field_options
                .map(call_option => call_option.field)
                .filter(field => this.hasRecordValue(this.record[field]))
            this.debugLog('openRecord', {
                index,
                code: this.record?.code || null,
                description_preview: this.previewText(this.record?.description),
                description_length: this.getLength(this.record?.description),
                editor_description_length: this.getLength(this.record_description)
            })
        },
        closeRecord() {
            this.record = null
            this.record_index = null
            this.record_description = ''
            this.destroyDescriptionEditor()
            this.selected_call_field = ''
            this.record_visible_call_fields = []
        },
        deleteRecord() {
            if (this.record_index === null) {
                return
            }
            this.flow.splice(this.record_index, 1)
            this.closeRecord()
        },
        saveRecord() {
            if (this.record_index === null) {
                return
            }
            const next_record = {
                ...this.record
            }

            this.call_field_options.forEach(call_option => {
                const is_visible = this.record_visible_call_fields.includes(call_option.field)
                if (!is_visible || !this.hasRecordValue(next_record[call_option.field])) {
                    next_record[call_option.field] = null
                } else {
                    next_record[call_option.field] = String(next_record[call_option.field]).trim()
                }
            })

            next_record.description = this.normalizeDescription(this.record_description)
            this.debugLog('saveRecord.beforeSplice', {
                index: this.record_index,
                code: next_record?.code || null,
                description_preview: this.previewText(next_record?.description),
                description_length: this.getLength(next_record?.description),
                editor_description_length: this.getLength(this.record_description)
            })

            this.flow.splice(this.record_index, 1, next_record)
            this.closeRecord()
        },
        createDescriptionEditor(content = '') {
            this.destroyDescriptionEditor()

            this.description_editor = new TiptapEditor({
                content: content || '',
                extensions: [
                    StarterKit,
                    Link.configure({
                        openOnClick: false,
                        autolink: true,
                        defaultProtocol: 'https'
                    })
                ],
                onUpdate: ({ editor }) => {
                    const html = editor.getHTML()
                    this.record_description = html
                    if (this.record) {
                        this.record.description = html
                    }
                }
            })
        },
        destroyDescriptionEditor() {
            if (this.description_editor) {
                this.description_editor.destroy()
                this.description_editor = null
            }
        },
        toggleDescriptionBold() {
            this.description_editor?.chain().focus().toggleBold().run()
        },
        toggleDescriptionItalic() {
            this.description_editor?.chain().focus().toggleItalic().run()
        },
        toggleDescriptionHeading(level) {
            this.description_editor?.chain().focus().toggleHeading({ level }).run()
        },
        toggleDescriptionBulletList() {
            this.description_editor?.chain().focus().toggleBulletList().run()
        },
        toggleDescriptionOrderedList() {
            this.description_editor?.chain().focus().toggleOrderedList().run()
        },
        setDescriptionLink() {
            if (!this.description_editor) {
                return
            }

            const current_href = this.description_editor.getAttributes('link')?.href || ''
            const href = window.prompt('Введите URL', current_href)
            if (href === null) {
                return
            }
            const normalized_href = String(href).trim()
            if (!normalized_href) {
                this.description_editor.chain().focus().unsetLink().run()
                return
            }

            this.description_editor.chain().focus().setLink({ href: normalized_href }).run()
        },
        unsetDescriptionLink() {
            this.description_editor?.chain().focus().unsetLink().run()
        },
        clearDescriptionFormatting() {
            this.description_editor?.chain().focus().clearNodes().unsetAllMarks().run()
        },
        isDescriptionBoldActive() {
            return !!this.description_editor?.isActive('bold')
        },
        isDescriptionItalicActive() {
            return !!this.description_editor?.isActive('italic')
        },
        isDescriptionHeadingActive(level) {
            return !!this.description_editor?.isActive('heading', { level })
        },
        isDescriptionBulletListActive() {
            return !!this.description_editor?.isActive('bulletList')
        },
        isDescriptionOrderedListActive() {
            return !!this.description_editor?.isActive('orderedList')
        },
        normalizeDescription(value) {
            if (value === null || value === undefined) {
                return null
            }

            const html = String(value).trim()
            if (
                html === ''
                || html === '<p><br></p>'
                || html === '<div><br></div>'
                || html === '<p></p>'
            ) {
                return null
            }

            return html
        },
        hasRecordValue(value) {
            return value !== null && value !== undefined && String(value).trim() !== ''
        },
        addCallField() {
            if (!this.record || !this.selected_call_field) {
                return
            }

            if (!this.record_visible_call_fields.includes(this.selected_call_field)) {
                this.record_visible_call_fields.push(this.selected_call_field)
            }

            if (!this.hasRecordValue(this.record[this.selected_call_field])) {
                this.record[this.selected_call_field] = ''
            }

            this.selected_call_field = ''
        },
        removeCallField(field) {
            if (!this.record) {
                return
            }

            this.record_visible_call_fields = this.record_visible_call_fields.filter(item => item !== field)
            this.record[field] = null
        },
        syncProcessInput() {
            const input = document.querySelector('#Form-field-Flow-process_app')
            if (!input) {
                this.debugLog('syncProcessInput.missingInput', {})
                return
            }
            input.value = JSON.stringify(this.flow || [])
            this.debugLog('syncProcessInput.updated', {
                json_length: this.getLength(input.value),
                has_description_substring: input.value.includes('description')
            })
        },
        createNewProcess() {
            this.api({
                api: 'ProcessApi:createNewProcess',
                data: {
                    flow_id: this.flow_id
                },
                then: response => {
                    if (response.success) {
                        this.getFlow()
                    }
                }
            })
        },
        getFlow() {
            this.api({
                api: 'ProcessApi:getFlowData',
                data: {
                    flow_id: this.flow_id
                },
                then: response => {
                    this.flow = response.flow
                    const with_description_count = Array.isArray(response.flow)
                        ? response.flow.filter(item => this.hasRecordValue(item?.description)).length
                        : 0
                    this.debugLog('getFlow.loaded', {
                        total: Array.isArray(response.flow) ? response.flow.length : 0,
                        with_description_count
                    })
                }
            })
        },
        debugLog(label, payload = {}) {
            try {
                // Временная отладка: помогает увидеть где теряется description.
                console.log('[ProcessAppDebug]', label, payload)
            } catch (e) {
                // ignore
            }
        },
        previewText(value) {
            if (value === null || value === undefined) {
                return null
            }
            return String(value).slice(0, 140)
        },
        getLength(value) {
            if (value === null || value === undefined) {
                return 0
            }
            return String(value).length
        },
        // Запуск/остановка процесса
        runProcess(process_uid)
        {
            if (this.pending_actions[process_uid]) {
                return
            }
            this.pending_actions = {
                ...this.pending_actions,
                [process_uid]: true
            }
            this.api({
                api: 'ProcessApi:runProcess',
                data: {
                    flow_id: this.flow_id,
                    process_uid: process_uid
                }
            })
        },
        moveProcess(process_uid, direction)
        {
            this.api({
                api: 'ProcessApi:moveProcess',
                data: {
                    flow_id: this.flow_id,
                    process_uid: process_uid,
                    direction: direction
                },
                then: response => {
                    if (response.success) {
                        this.getFlow()
                    }
                }
            })
        },
        getFlowStates()
        {
            this.api({
                api: 'ProcessApi:getFlowStates',
                data: {
                    flow_id: this.flow_id
                },
                then: response => {
                    this.flow_states = response.states
                    const nextPending = { ...this.pending_actions }
                    this.flow_states.forEach(state => {
                        if (state?.code && nextPending[state.code]) {
                            delete nextPending[state.code]
                        }
                    })
                    this.pending_actions = nextPending
                }
            })
        },
        killProcess(process_uid)
        {
            this.api({
                api: 'ProcessApi:killProcess',
                data: {
                    flow_id: this.flow_id,
                    process_uid: process_uid
                },
                then: response => {
                    if (response.success) {
                        this.getFlow()
                    }
                }
            })
        },
        clearProcess(process_uid)
        {
            this.api({
                api: 'ProcessApi:clearProcess',
                data: {
                    flow_id: this.flow_id,
                    process_uid: process_uid
                },
                then: response => {
                    if (response.success) {
                        this.getFlow()
                    }
                }
            })
        }
    }
}
</script>
<style lang="scss">
.process-app {
    padding: 16px;
}

.process-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    margin-bottom: 12px;
    background: var(--oc-toolbar-bg);
    border: 1px solid var(--oc-toolbar-border);
    border-radius: 4px;
}

.process-controls .backend-toolbar-button {
    font-size: 13px;
    border: 1px solid var(--bs-border-color);
    background: var(--oc-toolbar-bg);
    padding: 6px 10px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.process-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.process-list__empty {
    padding: 12px 0;
}

.process-row {
    display: grid;
    grid-template-columns: 30px 50px 300px 300px 450px 1fr max-content;
    gap: 12px;
    align-items: center;
    background: var(--oc-form-control-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    padding: 10px 12px;
}

.process-row__cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    text-align: left;
}

.process-row__spacer {
    width: 100%;
}

.process-row__index {
    align-items: center;
}

.process-row__move {
    align-items: stretch;
    width: 50px;
    max-width: 50px;
    gap: 4px;
}

.process-row__move-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 1 1 auto;
    width: 100%;
    min-height: 24px;
    padding: 2px 0;
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    background: var(--oc-form-control-bg);
    color: var(--oc-text-color);
}

.process-row__move-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.process-row__label {
    font-size: 11px;
    color: #0072c0;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    font-weight: bold;
}

.process-row__value {
    font-weight: 600;
}

.process-row__text {
    color: var(--oc-text-color);
    white-space: normal;
    word-break: break-word;
}

.process-row__status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    border: 1px solid var(--bs-border-color);
}

.process-row__status--on {
    background: var(--bs-success);
}

.process-row__status--off {
    background: var(--bs-secondary);
}

.process-row__status--error {
    background: var(--bs-danger);
}

.process-row__gear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    background: var(--oc-form-control-bg);
    color: var(--oc-text-color);
}

.process-row__actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    width: max-content;
}

.process-row__progress {
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
    width: 120px;
    flex: 0 0 120px;
}

.process-row__progress__bar {
    height: 6px;
    background: var(--bs-border-color);
    border-radius: 4px;
    overflow: hidden;
}

.process-row__progress__fill {
    display: block;
    height: 100%;
    background: var(--bs-primary);
    width: 0%;
    transition: width 0.2s ease;
}

.process-row__progress__text {
    font-size: 11px;
    color: var(--oc-text-color);
    text-align: right;
    white-space: nowrap;
}

.process-row__action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    background: var(--oc-form-control-bg);
    color: var(--oc-text-color);
}

.process-row__action:hover {
    background: var(--oc-toolbar-bg);
}

.process-row__gear:hover {
    background: var(--oc-toolbar-bg);
}

.process-modal {
    display: grid;
    gap: 12px;
}

.process-modal__error {
    border: 1px solid rgba(220, 53, 69, 0.4);
    background: rgba(220, 53, 69, 0.06);
    border-radius: 6px;
    padding: 12px;
    display: grid;
    gap: 8px;
}

.process-modal__error__title {
    font-weight: 600;
    color: #b02a37;
}

.process-modal__error__text {
    white-space: pre-wrap;
    color: #6b1f28;
}

.process-modal__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.process-modal__rich {
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    background: var(--oc-form-control-bg);
    overflow: hidden;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.process-modal__rich-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 10px;
    border-bottom: 1px solid var(--bs-border-color);
    background: var(--oc-toolbar-bg);
}

.process-modal__rich-toolbar .btn {
    min-width: 30px;
    width: 30px;
    height: 28px;
    padding: 0;
    font-size: 12px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.process-modal__rich-toolbar .btn i {
    font-size: 13px;
}

.process-modal__rich-toolbar .btn.is-active {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff;
}

.process-modal__tool-level {
    position: absolute;
    right: 2px;
    bottom: 1px;
    font-size: 8px;
    line-height: 1;
    font-weight: 700;
}

.process-modal__rich-content {
    min-height: 200px;
    background: var(--oc-form-control-bg);
}

.process-modal__rich-content .ProseMirror {
    min-height: 200px;
    padding: 14px 16px;
    outline: none;
    color: var(--oc-text-color);
    font-family: var(--oc-font-family, inherit);
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}

.process-modal__rich-content .ProseMirror p {
    margin: 0 0 10px;
}

.process-modal__rich-content .ProseMirror p:last-child {
    margin-bottom: 0;
}

.process-modal__rich--empty .process-modal__rich-content {
    min-height: 44px;
}

.process-modal__rich--empty .ProseMirror {
    min-height: 44px;
    padding-top: 9px;
    padding-bottom: 9px;
}

.process-modal__rich-content .ProseMirror h1,
.process-modal__rich-content .ProseMirror h2,
.process-modal__rich-content .ProseMirror h3 {
    color: var(--oc-text-color);
    line-height: 1.35;
    margin: 14px 0 10px;
    font-weight: 600;
}

.process-modal__rich-content .ProseMirror h1 {
    font-size: 24px;
}

.process-modal__rich-content .ProseMirror h2 {
    font-size: 20px;
}

.process-modal__rich-content .ProseMirror h3 {
    font-size: 17px;
}

.process-modal__rich-content .ProseMirror ul,
.process-modal__rich-content .ProseMirror ol {
    margin: 0 0 12px;
    padding-left: 22px;
}

.process-modal__rich-content .ProseMirror li {
    margin: 2px 0;
}

.process-modal__rich-content .ProseMirror a {
    color: var(--bs-primary);
    text-decoration: underline;
}

.process-modal__rich-content .ProseMirror blockquote {
    margin: 12px 0;
    padding: 8px 12px;
    border-left: 3px solid var(--bs-border-color);
    color: var(--oc-toolbar-text);
    background: rgba(0, 0, 0, 0.03);
}

.process-modal__label {
    font-size: 12px;
    color: var(--oc-toolbar-text);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.process-modal__switch {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    width: fit-content;
    padding: 8px 10px;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    background: var(--oc-form-control-bg);
}

.process-modal__switch-control {
    position: relative;
    width: 42px;
    height: 24px;
    flex: 0 0 auto;
}

.process-modal__switch-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    cursor: pointer;
    z-index: 2;
}

.process-modal__switch-slider {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: #d1d5db;
    border: 1px solid #c7cdd6;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.process-modal__switch-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease;
}

.process-modal__switch-input:checked + .process-modal__switch-slider {
    background: var(--bs-success);
    border-color: var(--bs-success);
}

.process-modal__switch-input:checked + .process-modal__switch-slider::after {
    transform: translateX(18px);
}

.process-modal__switch-input:focus-visible + .process-modal__switch-slider {
    outline: 2px solid rgba(13, 110, 253, 0.45);
    outline-offset: 2px;
}

.process-modal__editor {
    display: grid;
    gap: 12px;
}

.process-modal__calls {
    display: grid;
    gap: 10px;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    padding: 12px;
    background: var(--oc-form-control-bg);
}

.process-modal__calls-header {
    display: grid;
    gap: 8px;
}

.process-modal__calls-actions {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: center;
}

.process-modal__calls-empty {
    font-size: 13px;
}

.process-modal__call-list {
    display: grid;
    gap: 8px;
}

.process-modal__call-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    padding: 10px;
    display: grid;
    gap: 8px;
    background: #f6f6f6;
}

.process-modal__call-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.process-modal__call-card-title {
    font-size: 12px;
    font-weight: 600;
}

.process-modal__remove {
    width: 28px;
    height: 28px;
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    background: var(--oc-form-control-bg);
    color: var(--oc-text-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.threes-modal__footer {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .process-row {
        grid-template-columns: 1fr 1fr;
        gap: 10px 12px;
    }

    .process-row__index {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
    }
    .process-row__actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}

@media (max-width: 480px) {
    .process-row {
        grid-template-columns: 1fr;
    }
    .process-row__actions {
        justify-content: flex-end;
    }
}

@media (max-width: 1500px) {
    .process-row {
        grid-template-columns: 1fr;
    }

    .process-row__spacer {
        display: none;
    }

    .process-row__cell--center {
        align-items: flex-start;
        text-align: left;
    }

    .process-row__gear {
        justify-self: flex-end;
    }
}
</style>
