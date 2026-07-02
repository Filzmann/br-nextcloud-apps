(function() {
    const weekday = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    const monthName = ['', 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function dateShort(date) {
        const parts = String(date).split('-');
        if (parts.length !== 3) {
            return date;
        }
        return parts[2] + '.' + parts[1] + '.';
    }

    function dayHeader(day) {
        return esc(weekday[day.weekday] || '') + '<span>' + esc(String(day.dayOfMonth)) + '</span>';
    }

    function monthHeader(day) {
        const label = monthName[day.month] || String(day.month);
        return esc(label) + '<span>' + esc(String(day.dayOfMonth)) + '</span>';
    }

    function statusLabel(status) {
        if (status === 'approved') {
            return 'genehmigt';
        }
        if (status === 'planned') {
            return 'geplant';
        }
        return '';
    }

    function showNotice(message) {
        const notice = byId('adp-notice');
        notice.hidden = !message;
        notice.textContent = message || '';
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.ui = { byId, esc, dateShort, dayHeader, monthHeader, statusLabel, showNotice };
})();
