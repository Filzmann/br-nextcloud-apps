<?php

declare(strict_types=1);

namespace OCA\BrTop\View;

final class UiComponents {
    private const PRESETS = [
        'newMeeting' => ['icon' => '+', 'label' => 'Neue Sitzung', 'class' => ''],
        'backSessions' => ['icon' => '&larr;', 'label' => 'Zurück', 'class' => ''],
        'createInvitation' => ['icon' => '&#9993;', 'label' => 'Ladung erzeugen', 'class' => ''],
        'editProtocol' => ['icon' => '&#9998;', 'label' => 'Protokoll bearbeiten', 'class' => ''],
        'addTop' => ['icon' => '+', 'label' => 'TOP hinzufügen', 'class' => 'brtop-icon-button-primary'],
        'close' => ['icon' => '&times;', 'label' => 'Schließen', 'class' => ''],
        'saveTop' => ['icon' => '&#10003;', 'label' => 'TOP speichern', 'class' => 'brtop-icon-button-primary'],
        'backDetail' => ['icon' => '&larr;', 'label' => 'Zur Sitzung', 'class' => ''],
        'generateProtocolDocument' => ['icon' => '&#10003;', 'label' => 'Protokolldokument erzeugen', 'class' => ''],
    ];

    public static function preset(string $preset, array $attrs = [], string $class = ''): string {
        if (!isset(self::PRESETS[$preset])) {
            throw new \InvalidArgumentException('Unbekanntes BRTop-UI-Preset: ' . $preset);
        }

        $button = self::PRESETS[$preset];

        return self::iconButton(
            (string)$button['icon'],
            (string)$button['label'],
            $attrs,
            trim((string)$button['class'] . ' ' . $class)
        );
    }

    public static function iconButton(string $icon, string $label, array $attrs = [], string $class = ''): string {
        $classes = trim('brtop-icon-button ' . $class);
        $attrs = array_merge([
            'type' => 'button',
            'class' => $classes,
            'title' => $label,
            'aria-label' => $label,
        ], $attrs);

        return '<button' . self::attributes($attrs) . '>'
            . '<span class="brtop-icon" aria-hidden="true">' . $icon . '</span>'
            . '<span class="brtop-button-label">' . self::esc($label) . '</span>'
            . '</button>';
    }

    private static function attributes(array $attrs): string {
        $html = '';

        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $html .= $value === true
                ? ' ' . self::esc((string)$name)
                : ' ' . self::esc((string)$name) . '="' . self::esc((string)$value) . '"';
        }

        return $html;
    }

    private static function esc(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
