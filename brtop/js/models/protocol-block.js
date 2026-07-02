(function() {
    class ProtocolBlock {
        constructor(data = {}) {
            this.id = data.id ?? null;
            this.meeting_id = data.meeting_id || data.meetingId || 0;
            this.top_id = data.top_id || data.topId || 0;
            this.block_position = data.block_position || data.blockPosition || 0;
            this.block_type = data.block_type || data.blockType || 'text';
            this.content = data.content || '';
            this.created_at = data.created_at || data.createdAt || '';
            this.updated_at = data.updated_at || data.updatedAt || '';
        }

        static fromApi(data) {
            return data instanceof ProtocolBlock ? data : new ProtocolBlock(data || {});
        }

        isTextBlock() {
            return this.block_type === 'text';
        }

        toApi() {
            return {
                id: this.id,
                meeting_id: this.meeting_id,
                top_id: this.top_id,
                block_position: this.block_position,
                block_type: this.block_type,
                content: this.content,
                created_at: this.created_at,
                updated_at: this.updated_at
            };
        }
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.models = window.BRTop.models || {};
    window.BRTop.models.ProtocolBlock = ProtocolBlock;
})();
