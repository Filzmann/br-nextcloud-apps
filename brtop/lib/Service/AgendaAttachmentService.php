<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class AgendaAttachmentService {
    public function normalizeInvitationNote(string $note): string {
        return implode("\n", $this->lines($note));
    }

    public function normalizeAttachmentPaths(string $raw): string {
        $paths = [];

        foreach ($this->lines($raw) as $path) {
            $this->assertSafeAttachmentPath($path);
            $paths[$path] = true;
        }

        return implode("\n", array_keys($paths));
    }

    public function attachmentPathLines(string $raw): array {
        return $this->lines($this->normalizeAttachmentPaths($raw));
    }

    private function lines(string $raw): array {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $raw) ?: []), static fn(string $line): bool => $line !== ''));
    }

    private function assertSafeAttachmentPath(string $path): void {
        if (str_contains($path, "\0") || preg_match('/[[:cntrl:]]/', $path)) {
            throw new \InvalidArgumentException('Anhangpfade dürfen keine Steuerzeichen enthalten.');
        }

        $parts = preg_split('#[\\\\/]#', $path) ?: [];
        if (in_array('..', $parts, true)) {
            throw new \InvalidArgumentException('Anhangpfade dürfen keine relativen ..-Segmente enthalten.');
        }
    }
}
