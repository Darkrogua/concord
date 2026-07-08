import axios from 'axios'

axios.defaults.withCredentials = true
axios.defaults.xsrfCookieName = 'XSRF-TOKEN'
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN'

export function actApi() {
    const getCookie = (name) => {
        const cookies = document.cookie ? document.cookie.split(';') : []
        const prefix = `${name}=`
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim()
            if (cookie.startsWith(prefix)) {
                return decodeURIComponent(cookie.substring(prefix.length))
            }
        }
        return null
    }

    const syncCsrfMetaFromCookie = () => {
        const csrf_token = getCookie('XSRF-TOKEN')
        if (!csrf_token) {
            return
        }

        const csrf_meta = document.querySelector('meta[name="csrf-token"]')
        if (csrf_meta) {
            csrf_meta.setAttribute('content', csrf_token)
        }
    }

    const csrfHeaders = () => {
        syncCsrfMetaFromCookie()

        const csrf_meta = document.querySelector('meta[name="csrf-token"]')
        const csrf_token = csrf_meta
            ? csrf_meta.getAttribute('content')
            : getCookie('XSRF-TOKEN')

        if (!csrf_token) {
            return {}
        }

        return {
            'X-CSRF-TOKEN': csrf_token,
            'X-OCTOBER-REQUEST-TOKEN': csrf_token,
        }
    }

    return function api(opts) {
        const data = opts.data || null
        const api_url = opts.api ? `/act.api/${opts.api}` : opts.url
        const axios_options = {
            headers: csrfHeaders(),
            withCredentials: true,
        }

        const handleResponse = (response) => {
            syncCsrfMetaFromCookie()
            if (opts.then) {
                opts.then(response)
            }
        }

        const handleError = (error) => {
            const payload = error?.response?.data
            if (typeof payload === 'string') {
                try {
                    handleResponse(JSON.parse(payload))
                    return
                } catch (e) {
                    // ignore
                }
            }
            if (payload && opts.then) {
                handleResponse(payload)
                return
            }
            if (opts.catch) {
                opts.catch(error)
            } else {
                console.error(error)
            }
        }

        if (!data) {
            axios.get(api_url, axios_options)
                .then(res => handleResponse(res.data))
                .catch(handleError)
        } else if (typeof FormData !== 'undefined' && data instanceof FormData) {
            axios.post(api_url, data, axios_options)
                .then(res => handleResponse(res.data))
                .catch(handleError)
        } else {
            axios.post(api_url, data, axios_options)
                .then(res => handleResponse(res.data))
                .catch(handleError)
        }
    }
}

export function uploadActAsset({ api: apiName = 'Assets:upload', act_id, block_id, file, then, catch: onCatch }) {
    const form = new FormData()
    form.append('act_id', act_id)
    form.append('block_id', block_id)
    form.append('file', file)

    actApi()({
        api: apiName,
        data: form,
        then,
        catch: onCatch,
    })
}
