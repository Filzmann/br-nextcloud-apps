(function() {
    async function request(url, options = {}) {
        const response = await fetch(OC.generateUrl('/apps/adplaner' + url), {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken,
                ...(options.headers || {})
            }
        });

        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            data = { raw: text };
        }

        if (!response.ok) {
            const error = new Error(data && data.message ? data.message : ('HTTP ' + response.status));
            error.data = data;
            error.status = response.status;
            throw error;
        }

        return data;
    }

    function encode(value) {
        return encodeURIComponent(String(value));
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.api = { request, encode };
})();
