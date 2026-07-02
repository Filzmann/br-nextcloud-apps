<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class DocumentDateFormatter {
    public function germanDate(string $date): string {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        return date('d.m.Y', $ts);
    }
}
