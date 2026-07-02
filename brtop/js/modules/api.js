(function() {
    async function request(url, options = {}) {
        const response = await fetch(OC.generateUrl('/apps/brtop' + url), {
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
            const msg = data.ok === false && data.message ? data.message : ('HTTP ' + response.status);
            const error = new Error(msg);
            error.data = data;
            error.status = response.status;
            throw error;
        }

        return data;
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.api = { request };
})();
