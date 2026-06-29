<?php

namespace App\Support;

use App\Models\SupportRecord;

final class SupportReportMailBodyFormatter
{
    private const SECTION_HEADER_COLOR = '#0942a3';

    public static function supportContentHtml(SupportRecord $record, string $reportMode): string
    {
        $text = filled($record->TO_Account)
            ? (string) $record->TO_Account
            : (filled($record->Issue) ? (string) $record->Issue : '');

        if ($text === '') {
            return '<p style="margin:0;">—</p>';
        }

        if (
            $reportMode === 'teacher'
            && $record->Support_Type === config('coach_teacher_visit.support_type_label')
        ) {
            return self::visitToAccountHtml($text);
        }

        return '<p style="margin:0; line-height:1.5;">'.self::textWithLineBreaks($text).'</p>';
    }

    /** Escape user-entered text while making line breaks reliable in HTML emails. */
    public static function textWithLineBreaks(?string $text, string $empty = '—'): string
    {
        $value = filled($text) ? (string) $text : $empty;

        return nl2br(e($value), false);
    }

    private static function visitToAccountHtml(string $text): string
    {
        /** @var array<string, string> $labels */
        $labels = config('coach_teacher_visit.to_account_section_labels', []);
        $knownLabels = array_values($labels);

        $blocks = preg_split("/\n\n+/", trim($text)) ?: [];
        $html = '';
        $isFirst = true;

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = explode("\n", $block, 2);
            $firstLine = trim($lines[0] ?? '');
            $body = trim($lines[1] ?? '');

            if (! in_array($firstLine, $knownLabels, true)) {
                $html .= self::plainBlockHtml($block, $isFirst);
                $isFirst = false;

                continue;
            }

            $html .= self::sectionHtml($firstLine, $body, $isFirst);
            $isFirst = false;
        }

        return $html !== ''
            ? $html
            : '<p style="margin:0; line-height:1.5;">'.self::textWithLineBreaks($text).'</p>';
    }

    private static function sectionHtml(string $label, string $body, bool $isFirst): string
    {
        $marginTop = $isFirst ? '0' : '14px';
        $headerStyle = 'margin:'.$marginTop.' 0 6px; font-weight:bold; color:'.self::SECTION_HEADER_COLOR.'; font-size:15px;';
        $bodyStyle = 'margin:0; line-height:1.5;';

        return '<div style="'.$headerStyle.'">'.e($label).'</div>'
            .'<div style="'.$bodyStyle.'">'.self::textWithLineBreaks($body !== '' ? $body : null).'</div>';
    }

    private static function plainBlockHtml(string $block, bool $isFirst): string
    {
        $marginTop = $isFirst ? '0' : '14px';

        return '<div style="margin:'.$marginTop.' 0 0; line-height:1.5;">'
            .self::textWithLineBreaks($block)
            .'</div>';
    }
}
