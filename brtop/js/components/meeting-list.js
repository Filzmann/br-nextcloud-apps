(function() {
    const {
        esc,
        fmtDate,
        fmtTime,
        buttonPresetHtml
    } = window.BRTop.ui;

    function sessionTableHtml(meetings) {
        if (meetings.length === 0) {
            return '<p>Noch keine Sitzung vorhanden.</p>';
        }

        const rows = meetings.map(meeting => `
            <tr>
                <td>${esc(fmtDate(meeting.meeting_date))}</td>
                <td>${esc(fmtTime(meeting.meeting_time))}</td>
                <td>${esc(meeting.title || 'ohne Titel')}</td>
                <td class="brtop-table-actions">
                    ${buttonPresetHtml('editMeeting', { 'data-id': meeting.id })}
                    ${buttonPresetHtml('deleteMeeting', { 'data-id': meeting.id })}
                </td>
            </tr>
        `).join('');

        return `
            <div class="brtop-table-wrap">
                <table class="brtop-session-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Uhrzeit</th>
                            <th>Titel</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.meetingList = { sessionTableHtml };
})();
