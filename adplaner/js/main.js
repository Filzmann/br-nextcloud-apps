(function() {
    const { request: api, encode } = window.ADPlaner.api;
    const { byId, esc, showNotice } = window.ADPlaner.ui;
    const { render: renderMonthPlan } = window.ADPlaner.monthPlan;
    const { render: renderVacationPlan } = window.ADPlaner.vacationPlan;
    const { render: renderSettingsPanel } = window.ADPlaner.settingsPanel;

    const state = {
        currentUser: null,
        teams: [],
        selectedTeamCode: '',
        month: '',
        year: '',
        activeView: 'month',
        monthPlan: null,
        vacationPlan: null,
        loading: false
    };

    function selectedTeam() {
        return state.teams.find(team => team.code === state.selectedTeamCode) || null;
    }

    function teamPath() {
        return '/api/teams/' + encode(state.selectedTeamCode);
    }

    async function init() {
        try {
            const data = await api('/api/state');
            state.currentUser = data.currentUser || null;
            state.teams = data.teams || [];
            state.month = data.defaultMonth || new Date().toISOString().slice(0, 7);
            state.year = String(data.defaultYear || new Date().getFullYear());
            state.selectedTeamCode = state.teams.length ? state.teams[0].code : '';

            renderChrome();
            if (state.selectedTeamCode) {
                await reloadActive();
            } else {
                showNotice('Keine Gruppe nach dem Muster ad-ASN-<Kuerzel> gefunden.');
                renderPanel();
            }
        } catch (e) {
            showNotice(e.message);
        }
    }

    function renderChrome() {
        const teamSelect = byId('team-select');
        teamSelect.innerHTML = state.teams.map(team => {
            const selected = team.code === state.selectedTeamCode ? ' selected' : '';
            return `<option value="${esc(team.code)}"${selected}>${esc(team.displayName || team.code)}</option>`;
        }).join('');
        teamSelect.disabled = state.teams.length === 0;

        byId('month-input').value = state.month;
        byId('year-input').value = state.year;

        document.querySelectorAll('.adp-tab').forEach(button => {
            button.classList.toggle('is-active', button.dataset.view === state.activeView);
        });
    }

    function renderPanel() {
        const panel = byId('adp-panel');
        if (state.loading) {
            panel.innerHTML = '<p class="adp-loading">Lade...</p>';
            return;
        }

        if (!state.selectedTeamCode) {
            panel.innerHTML = '<p>Keine Assistenznehmer-Gruppen.</p>';
            return;
        }

        if (state.activeView === 'vacation') {
            panel.innerHTML = renderVacationPlan(state.vacationPlan, state.currentUser);
            bindVacationForm();
            return;
        }

        if (state.activeView === 'settings') {
            panel.innerHTML = renderSettingsPanel(selectedTeam());
            bindSettingsForm();
            return;
        }

        panel.innerHTML = renderMonthPlan(state.monthPlan, state.currentUser);
    }

    async function reloadActive() {
        state.loading = true;
        renderPanel();
        showNotice('');

        try {
            if (state.activeView === 'vacation') {
                await loadVacation();
            } else if (state.activeView === 'settings') {
                await refreshState();
            } else {
                await loadMonth();
            }
        } catch (e) {
            showNotice(e.message);
        } finally {
            state.loading = false;
            renderChrome();
            renderPanel();
        }
    }

    async function refreshState() {
        const data = await api('/api/state');
        state.currentUser = data.currentUser || state.currentUser;
        state.teams = data.teams || [];
        if (!state.teams.some(team => team.code === state.selectedTeamCode)) {
            state.selectedTeamCode = state.teams.length ? state.teams[0].code : '';
        }
    }

    async function loadMonth() {
        state.monthPlan = await api(teamPath() + '/months/' + encode(state.month));
        const updatedTeam = state.monthPlan.team;
        state.teams = state.teams.map(team => team.code === updatedTeam.code ? updatedTeam : team);
    }

    async function loadVacation() {
        state.vacationPlan = await api(teamPath() + '/vacations/' + encode(state.year));
        const updatedTeam = state.vacationPlan.team;
        state.teams = state.teams.map(team => team.code === updatedTeam.code ? updatedTeam : team);
    }

    async function post(url, body = {}) {
        return api(url, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    }

    async function handlePanelClick(event) {
        const button = event.target instanceof Element ? event.target.closest('button[data-action]') : null;
        if (!button) {
            return;
        }

        const action = button.dataset.action;
        try {
            if (action === 'add-self') {
                await post(teamPath() + '/months/' + encode(state.month) + '/slots/' + encode(button.dataset.slotId) + '/candidates');
                await loadMonth();
            } else if (action === 'add-selected') {
                const select = byId('adp-panel').querySelector(`select[data-add-select="${CSS.escape(button.dataset.slotId)}"]`);
                await post(teamPath() + '/months/' + encode(state.month) + '/slots/' + encode(button.dataset.slotId) + '/candidates', {
                    targetUid: select ? select.value : ''
                });
                await loadMonth();
            } else if (action === 'remove-candidate') {
                await post(teamPath() + '/months/' + encode(state.month) + '/slots/' + encode(button.dataset.slotId) + '/candidates/remove', {
                    targetUid: button.dataset.targetUid || ''
                });
                await loadMonth();
            } else if (action === 'save-note') {
                const textarea = byId('adp-panel').querySelector(`textarea[data-note-date="${CSS.escape(button.dataset.date)}"]`);
                await post(teamPath() + '/months/' + encode(state.month) + '/days/' + encode(button.dataset.date) + '/note', {
                    note: textarea ? textarea.value : ''
                });
                await loadMonth();
            } else if (action === 'delete-vacation') {
                await post('/api/vacations/' + encode(button.dataset.requestId) + '/delete');
                await loadVacation();
            } else if (action === 'set-vacation-status') {
                await post(teamPath() + '/vacations/' + encode(state.year) + '/status', {
                    assistantUid: button.dataset.targetUid || '',
                    date: button.dataset.date || '',
                    status: button.dataset.status || 'planned'
                });
                await loadVacation();
            }
        } catch (e) {
            showNotice(e.message);
        } finally {
            renderPanel();
        }
    }

    function bindVacationForm() {
        const form = byId('vacation-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const data = new FormData(form);
            try {
                await post('/api/vacations', {
                    dateFrom: data.get('dateFrom') || '',
                    dateTo: data.get('dateTo') || '',
                    note: data.get('note') || ''
                });
                form.reset();
                await loadVacation();
                renderPanel();
            } catch (e) {
                showNotice(e.message);
            }
        });
    }

    function bindSettingsForm() {
        const form = byId('settings-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const data = new FormData(form);
            try {
                await post(teamPath() + '/settings', {
                    displayName: data.get('displayName') || '',
                    meetingDay: data.get('meetingDay') || '',
                    earlyStart: data.get('earlyStart') || '06:00',
                    lateStart: data.get('lateStart') || '14:00',
                    nightStart: data.get('nightStart') || '22:00',
                    enabledBeforeEarly: data.has('enabledBeforeEarly'),
                    enabledEarly: data.has('enabledEarly'),
                    enabledLate: data.has('enabledLate'),
                    enabledNight: data.has('enabledNight')
                });
                await refreshState();
                state.activeView = 'month';
                await loadMonth();
                renderChrome();
                renderPanel();
            } catch (e) {
                showNotice(e.message);
            }
        });
    }

    byId('team-select').addEventListener('change', async event => {
        state.selectedTeamCode = event.target.value;
        state.monthPlan = null;
        state.vacationPlan = null;
        await reloadActive();
    });

    byId('month-input').addEventListener('change', async event => {
        state.month = event.target.value;
        if (state.activeView === 'month') {
            await reloadActive();
        }
    });

    byId('year-input').addEventListener('change', async event => {
        state.year = event.target.value;
        if (state.activeView === 'vacation') {
            await reloadActive();
        }
    });

    document.querySelector('.adp-tabs').addEventListener('click', async event => {
        const button = event.target instanceof Element ? event.target.closest('button[data-view]') : null;
        if (!button) {
            return;
        }

        state.activeView = button.dataset.view || 'month';
        renderChrome();
        await reloadActive();
    });

    byId('adp-panel').addEventListener('click', handlePanelClick);

    init();
})();
