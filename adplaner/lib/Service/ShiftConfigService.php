<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

class ShiftConfigService {
    public function defaults(): array {
        return [
            'meetingDay' => '',
            'shiftStarts' => [
                'early' => '06:00',
                'late' => '14:00',
                'night' => '22:00',
            ],
            'enabledSegments' => [
                'before_early' => true,
                'early' => true,
                'late' => true,
                'night' => true,
            ],
        ];
    }

    public function normalize(array $settings): array {
        $defaults = $this->defaults();
        $shiftStarts = $settings['shiftStarts'] ?? [];
        $enabledSegments = $settings['enabledSegments'] ?? [];

        return [
            'meetingDay' => $this->normalizeOptionalDate((string)($settings['meetingDay'] ?? $defaults['meetingDay'])),
            'shiftStarts' => [
                'early' => $this->normalizeTime((string)($shiftStarts['early'] ?? $defaults['shiftStarts']['early'])),
                'late' => $this->normalizeTime((string)($shiftStarts['late'] ?? $defaults['shiftStarts']['late'])),
                'night' => $this->normalizeTime((string)($shiftStarts['night'] ?? $defaults['shiftStarts']['night'])),
            ],
            'enabledSegments' => [
                'before_early' => (bool)($enabledSegments['before_early'] ?? $defaults['enabledSegments']['before_early']),
                'early' => (bool)($enabledSegments['early'] ?? $defaults['enabledSegments']['early']),
                'late' => (bool)($enabledSegments['late'] ?? $defaults['enabledSegments']['late']),
                'night' => (bool)($enabledSegments['night'] ?? $defaults['enabledSegments']['night']),
            ],
        ];
    }

    public function segments(array $settings): array {
        $settings = $this->normalize($settings);
        $starts = $settings['shiftStarts'];
        $enabled = $settings['enabledSegments'];

        return [
            [
                'key' => 'before_early',
                'label' => '0 bis Frueh',
                'startsAt' => '00:00',
                'endsAt' => $starts['early'],
                'enabled' => $enabled['before_early'],
            ],
            [
                'key' => 'early',
                'label' => 'Frueh',
                'startsAt' => $starts['early'],
                'endsAt' => $starts['late'],
                'enabled' => $enabled['early'],
            ],
            [
                'key' => 'late',
                'label' => 'Spaet',
                'startsAt' => $starts['late'],
                'endsAt' => $starts['night'],
                'enabled' => $enabled['late'],
            ],
            [
                'key' => 'night',
                'label' => 'Nacht bis 24',
                'startsAt' => $starts['night'],
                'endsAt' => '24:00',
                'enabled' => $enabled['night'],
            ],
        ];
    }

    public function monthDays(string $month): array {
        $month = $this->normalizeMonth($month);
        $first = new \DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $days = [];

        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'dayOfMonth' => (int)$day->format('j'),
                'weekday' => (int)$day->format('N'),
            ];
        }

        return $days;
    }

    public function yearDays(int $year): array {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Das Jahr ist ausserhalb des erlaubten Bereichs.');
        }

        $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $last = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
        $days = [];

        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'dayOfYear' => (int)$day->format('z') + 1,
                'dayOfMonth' => (int)$day->format('j'),
                'month' => (int)$day->format('n'),
                'weekday' => (int)$day->format('N'),
            ];
        }

        return $days;
    }

    public function normalizeMonth(string $month): string {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Der Monat muss im Format JJJJ-MM angegeben werden.');
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        if ($year < 2000 || $year > 2100 || $monthNumber < 1 || $monthNumber > 12) {
            throw new \InvalidArgumentException('Der Monat ist ausserhalb des erlaubten Bereichs.');
        }

        return sprintf('%04d-%02d', $year, $monthNumber);
    }

    public function normalizeDate(string $date): string {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Das Datum muss im Format JJJJ-MM-TT angegeben werden.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Das Datum ist ungueltig.');
        }

        return $date;
    }

    private function normalizeTime(string $time): string {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new \InvalidArgumentException('Schichtzeiten muessen im Format HH:MM angegeben werden.');
        }

        return $time;
    }

    private function normalizeOptionalDate(string $date): string {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        return $this->normalizeDate($date);
    }
}
