import axios from 'axios';
import md5 from 'md5';

export function chubApi() {
    const requests_register = {}

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

    return function api(opts) {
        const data = opts.data || null;
        
        const csrf_meta = document.querySelector('meta[name="csrf-token"]')
        const csrf_token = csrf_meta ? csrf_meta.getAttribute('content') : getCookie('XSRF-TOKEN')
        const axios_options = {}

        if (csrf_token) {
            axios_options.headers = {
                'X-CSRF-TOKEN': csrf_token,
                'X-OCTOBER-REQUEST-TOKEN': csrf_token
            }
        }

        const api_url = opts.api ? `/chub.api/${opts.api}` : opts.url
        const request_key = md5(api_url + JSON.stringify(data))

        if (requests_register[request_key]) {
            return;
        }

        console.log(`Chub query [${request_key}]: ${api_url}`, data)

        requests_register[request_key] = setTimeout(() => {
            if (requests_register[request_key]) {
                // Включить прелоадер
            }
        }, 2000);

        const handleResponse = (response) => {
            delete requests_register[request_key];
            // Включить прелоадер

            console.log(`Chub response [${request_key}]: ${api_url}`, response)

            if (response.messages) {
                // Отправить сообщения
            }

            if (opts.then) {
                opts.then(response);
            }
        }

        const handleError = (error) => {
            delete requests_register[request_key];
            // Выключить прелоадер
            console.error(error);
        }

        if (!data) {
            axios.get(api_url, axios_options)
                .then(res => handleResponse(res.data)).catch(handleError)
        } else {
            axios.post(api_url, data, axios_options)
                .then(res => handleResponse(res.data)).catch(handleError)
        }
    }
}
