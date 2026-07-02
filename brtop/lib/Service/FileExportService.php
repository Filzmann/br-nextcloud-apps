<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Model\Meeting;
use OCP\Files\IRootFolder;

class FileExportService {
    public function __construct(
        private IRootFolder $rootFolder
    ) {
    }

    public function meetingFolder(array|Meeting $meeting): string {
        $meetingData = $this->meetingData($meeting);

        return 'BR-Sitzungen/' . $meetingData['meeting_date'] . ' - ' . $this->safeName((string)$meetingData['title']);
    }

    public function ensureFolder(string $uid, string $path): void {
        $userFolder = $this->rootFolder->getUserFolder($uid);
        $parts = array_filter(explode('/', $path));
        $current = $userFolder;

        foreach ($parts as $part) {
            if (!$current->nodeExists($part)) {
                $current = $current->newFolder($part);
            } else {
                $current = $current->get($part);
            }
        }
    }

    public function putUserFile(string $uid, string $path, string $content): void {
        $userFolder = $this->rootFolder->getUserFolder($uid);

        $parts = explode('/', $path);
        $filename = array_pop($parts);
        $folderPath = implode('/', $parts);

        $this->ensureFolder($uid, $folderPath);
        $folder = $userFolder->get($folderPath);

        if ($folder->nodeExists($filename)) {
            $folder->get($filename)->putContent($content);
        } else {
            $folder->newFile($filename, $content);
        }
    }

    public function safeName(string $name): string {
        $name = preg_replace('/[^A-Za-z0-9äöüÄÖÜß_\- ]/u', '', $name) ?? '';
        $name = preg_replace('/\s+/', '_', trim($name)) ?? '';

        return $name ?: 'ohne_titel';
    }

    private function meetingData(array|Meeting $meeting): array {
        return $meeting instanceof Meeting ? $meeting->toRepositoryData() : $meeting;
    }
}
