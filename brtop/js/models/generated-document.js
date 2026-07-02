(function() {
    class GeneratedDocument {
        constructor(data = {}) {
            this.id = data.id ?? null;
            this.meeting_id = data.meeting_id || data.meetingId || 0;
            this.document_type = data.document_type || data.documentType || '';
            this.title = data.title || '';
            this.file_path = data.file_path || data.filePath || '';
            this.created_at = data.created_at || data.createdAt || '';
        }

        static fromApi(data) {
            return data instanceof GeneratedDocument ? data : new GeneratedDocument(data || {});
        }

        displayTitle() {
            return String(this.title || '').trim() || this.typeLabel();
        }

        typeLabel() {
            const labels = {
                invitation_email: 'Einladung E-Mail',
                invitation_markdown: 'Ladung',
                invitation_recipients: 'Ladungsliste',
                protocol_markdown: 'Protokollvorlage',
                protocol_odt: 'Protokollvorlage ODT',
                resolution_markdown: 'Beschlussdokument'
            };

            return labels[this.document_type] || 'Dokument';
        }

        toApi() {
            return {
                id: this.id,
                meeting_id: this.meeting_id,
                document_type: this.document_type,
                title: this.title,
                file_path: this.file_path,
                created_at: this.created_at
            };
        }
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.models = window.BRTop.models || {};
    window.BRTop.models.GeneratedDocument = GeneratedDocument;
})();
