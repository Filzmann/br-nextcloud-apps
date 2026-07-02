(function() {
    const { ProtocolBlock } = window.BRTop.models;

    class AgendaItem {
        constructor(data = {}) {
            this.id = data.id ?? null;
            this.meeting_id = data.meeting_id ?? data.meetingId ?? null;
            this.position = Number(data.position || 0);
            this.type = data.type || 'other';
            this.subject = data.subject || '';
            this.person_name = data.person_name || data.personName || '';
            this.legal_basis = data.legal_basis || data.legalBasis || '';
            this.requires_resolution = Number(data.requires_resolution ?? data.requiresResolution ?? 0);
            this.resolution_text = data.resolution_text || data.resolutionText || '';
            this.parent_id = data.parent_id ?? data.parentId ?? null;
            this.level = Math.min(3, Math.max(1, Number(data.level || 1)));
            this.agenda_item_kind = data.agenda_item_kind || data.agendaItemKind || '';
            this.protocol_content = data.protocol_content || data.protocolContent || '';
            this.invitation_note = data.invitation_note || data.invitationNote || '';
            this.attachment_paths = data.attachment_paths || data.attachmentPaths || '';
            this.resolution_count = Math.max(0, Number(data.resolution_count || data.resolutionCount || 0));
            this.created_at = data.created_at || data.createdAt || '';
            this.agenda_number = data.agenda_number || data.agendaNumber || '';
            this.protocol_blocks = Array.isArray(data.protocol_blocks) ? data.protocol_blocks.map(ProtocolBlock.fromApi) : [];
        }

        static fromApi(data) {
            return data instanceof AgendaItem ? data : new AgendaItem(data || {});
        }

        agendaNumberLabel() {
            const number = String(this.agenda_number || this.position || '').trim();
            return number === '' ? '' : number + '.';
        }

        kind() {
            if (['section', 'report', 'discussion', 'resolution'].includes(this.agenda_item_kind)) {
                return this.agenda_item_kind;
            }

            return this.requires_resolution === 1 ? 'resolution' : 'discussion';
        }

        kindLabel() {
            if (this.kind() === 'section') {
                return 'Gliederung';
            }
            if (this.kind() === 'report') {
                return 'Bericht';
            }
            if (this.isResolutionItem()) {
                const count = this.resolutionCount();
                return count > 1 ? `${count} Beschlüsse` : 'Beschluss';
            }
            return 'Beratung';
        }

        isResolutionItem() {
            return this.resolutionCount() > 0 || this.kind() === 'resolution' || this.requires_resolution === 1;
        }

        resolutionCount() {
            if (this.resolution_count > 0) {
                return this.resolution_count;
            }

            return this.requires_resolution === 1 ? 1 : 0;
        }

        attachmentLines() {
            return String(this.attachment_paths || '')
                .split(/\r?\n/)
                .map(line => line.trim())
                .filter(Boolean);
        }

        toApi() {
            return {
                id: this.id,
                meeting_id: this.meeting_id,
                position: this.position,
                type: this.type,
                subject: this.subject,
                person_name: this.person_name,
                legal_basis: this.legal_basis,
                requires_resolution: this.requires_resolution,
                resolution_text: this.resolution_text,
                parent_id: this.parent_id,
                level: this.level,
                agenda_item_kind: this.agenda_item_kind,
                protocol_content: this.protocol_content,
                invitation_note: this.invitation_note,
                attachment_paths: this.attachment_paths,
                resolution_count: this.resolution_count,
                created_at: this.created_at,
                agenda_number: this.agenda_number,
                protocol_blocks: this.protocol_blocks.map(block => block.toApi())
            };
        }
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.models = window.BRTop.models || {};
    window.BRTop.models.AgendaItem = AgendaItem;
})();
