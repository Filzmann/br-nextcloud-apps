<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

class ShiftConfigService {
    public function defaults(): array {
        return [
            'meetingDay' => '',
            'shifts' => [
                [
                    'key' => 'early',
                    'label' => 'Frueh',
                    'startsAt' => '08:00',
                    'endsAt' => '14:00',
                    'enabled' => true,
                ],
                [
                    'key' => 'late',
                    'label' => 'Spaet',
                    'startsAt' => '14:00',
                    'endsAt' => '20:00',
                    'enabled' => true,
                ],
                [
                    'key' => 'night',
                    'label' => 'Nacht',
                    'startsAt' => '20:00',
                    'endsAt' => '08:00',
                    'enabled' => true,
                ],
            ],
        ];
    }

    public function normalize(array $settings): array {
        $defaults = $this->defaults();
        $shifts = $settings['shifts'] ?? null;
        if (!is_array($shifts)) {
            $shifts = $this->legacyShifts($settings);
        }

        return [
            'meetingDay' => $this->normalizeOptionalDate((string)($settings['meetingDay'] ?? $defaults['meetingDay'])),
            'shifts' => $this->normalizeShifts($shifts),
        ];
    }

    public function segments(array $settings): array {
        return $this->normalize($settings)['shifts'];
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

    private function normalizeShifts(array $shifts): array {
        if ($shifts === []) {
            throw new \InvalidArgumentException('Mindestens eine Schicht muss konfiguriert sein.');
        }

        if (count($shifts) > 64) {
            throw new \InvalidArgumentException('Hoechstens 64 Schichten koennen konfiguriert werden.');
        }

        $normalized = [];
        $keys = [];
        foreach (array_values($shifts) as $index => $shift) {
            if (!is_array($shift)) {
                throw new \InvalidArgumentException('Schichten muessen als Liste uebergeben werden.');
            }

            $key = $this->normalizeShiftKey((string)($shift['key'] ?? ''), $index);
            if (isset($keys[$key])) {
                throw new \InvalidArgumentException('Schicht-Keys muessen eindeutig sein.');
            }

            $label = trim((string)($shift['label'] ?? ''));
            if ($label === '') {
                $label = 'Schicht ' . ($index + 1);
            }

            if (strlen($label) > 64) {
                throw new \InvalidArgumentException('Schichtnamen duerfen hoechstens 64 Zeichen lang sein.');
            }

            $startsAt = $this->normalizeTime((string)($shift['startsAt'] ?? ''));
            $endsAt = $this->normalizeTime((string)($shift['endsAt'] ?? ''));
            $this->assertShiftFitsInDay($startsAt, $endsAt);

            $keys[$key] = true;
            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
                'enabled' => $this->normalizeBoolean($shift['enabled'] ?? true),
            ];
        }

        return $normalized;
    }

    private function normalizeShiftKey(string $key, int $index): string {
        $key = trim($key);
        if ($key === '') {
            return 'shift_' . ($index + 1);
        }

        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $key)) {
            throw new \InvalidArgumentException('Schicht-Keys duerfen nur Buchstaben, Ziffern, Unterstriche und Bindestriche enthalten.');
        }

        return $key;
    }

    private function assertShiftFitsInDay(string $startsAt, string $endsAt): void {
        $duration = $this->minutesOfDay($endsAt) - $this->minutesOfDay($startsAt);
        if ($duration <= 0) {
            $duration += 1440;
        }

        if ($duration < 1 || $duration > 1440) {
            throw new \InvalidArgumentException('Eine Schicht muss innerhalb von 24 Stunden liegen.');
        }
    }

    private function minutesOfDay(string $time): int {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function normalizeBoolean(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
    }

    private function legacyShifts(array $settings): array {
        $defaults = $this->defaults();
        $shiftStarts = $settings['shiftStarts'] ?? [];
        $enabledSegments = $settings['enabledSegments'] ?? [];

        $earlyStart = $this->normalizeTime((string)($shiftStarts['early'] ?? '08:00'));
        $lateStart = $this->normalizeTime((string)($shiftStarts['late'] ?? '14:00'));
        $nightStart = $this->normalizeTime((string)($shiftStarts['night'] ?? '20:00'));

        if ($shiftStarts === [] && $enabledSegments === []) {
            return $defaults['shifts'];
        }

        return [
            [
                'key' => 'early',
                'label' => 'Frueh',
                'startsAt' => $earlyStart,
                'endsAt' => $lateStart,
                'enabled' => $this->normalizeBoolean($enabledSegments['early'] ?? true),
            ],
            [
                'key' => 'late',
                'label' => 'Spaet',
                'startsAt' => $lateStart,
                'endsAt' => $nightStart,
                'enabled' => $this->normalizeBoolean($enabledSegments['late'] ?? true),
            ],
            [
                'key' => 'night',
                'label' => 'Nacht',
                'startsAt' => $nightStart,
                'endsAt' => $earlyStart,
                'enabled' => $this->normalizeBoolean($enabledSegments['night'] ?? true),
            ],
        ];
    }

    private function normalizeOptionalDate(string $date): string {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        return $this->normalizeDate($date);
    }
}
