<?php

declare(strict_types=1);

namespace OCA\BrTop\Store;

use OCA\BrTop\Model\GeneratedDocument;
use OCA\BrTop\Repository\DocumentRepository;

class DocumentStore {
    public function __construct(
        private DocumentRepository $documentRepository
    ) {
    }

    public function forMeeting(int $meetingId): array {
        return array_map(
            static fn(array $row): GeneratedDocument => new GeneratedDocument($row),
            $this->documentRepository->findForMeeting($meetingId)
        );
    }
}
