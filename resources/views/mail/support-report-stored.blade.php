@php
    $fontPx = (int) config('support_report_mail.email_font_size_px', 14);
    $fs = $fontPx.'px';
    $fsHeading = ($fontPx + 3).'px';
    $wrap = 'word-break: break-word; overflow-wrap: anywhere;';
    $cellLabel = 'padding: 8px 10px; font-size: '.$fs.'; font-weight: bold; vertical-align: top; background: #f3f4f6; border: 1px solid #e5e7eb; '.$wrap;
    $cellValue = 'padding: 8px 10px; font-size: '.$fs.'; vertical-align: top; border: 1px solid #e5e7eb; '.$wrap;
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportSavedOpening }}</title>
</head>
<body style="font-family: sans-serif; line-height: 1.5; color: #111; font-size: {{ $fontPx }}px;">
<p style="margin-bottom: 12px; font-size:18px; font-weight: bold;color:#590091">{{ $reportSavedOpening }}</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 720px; font-size: {{ $fontPx }}px;">
    <colgroup>
        <col style="width: 22%;">
        <col style="width: 28%;">
        <col style="width: 22%;">
        <col style="width: 28%;">
    </colgroup>
    <tr>
        <td style="{{ $cellLabel }}">기관명</td>
        <td style="{{ $cellValue }}">{{ $supportRecord->Account_Name ?: '—' }}</td>
        <td style="{{ $cellLabel }}">작성자</td>
        <td style="{{ $cellValue }}">{{ $submitterLabel }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">지원일</td>
        <td style="{{ $cellValue }}">{{ $supportDate }}</td>
        <td style="{{ $cellLabel }}">시간</td>
        <td style="{{ $cellValue }}">{{ $meetTime }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">지원 유형</td>
        <td style="{{ $cellValue }}">{{ $supportRecord->Support_Type ?: '—' }}</td>
        <td style="{{ $cellLabel }}">{{ $reportAssigneeColumnLabel }}</td>
        <td style="{{ $cellValue }}">{{ $supportRecord->TR_Name ?: '—' }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">참석자</td>
        <td style="{{ $cellValue }}">{{ $supportRecord->Target ?: '—' }}</td>
        <td style="{{ $cellLabel }}">상태</td>
        <td style="{{ $cellValue }}">{{ $supportRecord->Status ?: '—' }}</td>
    </tr>
</table>

<h3 style="margin-top: 1.5em; margin-bottom: 0.25em; font-size: {{ $fsHeading }};color:rgb(9, 66, 163);">{{ $reportMode === 'teacher' ? '지원 내용' : '기관 이슈 및 논의 사항' }}</h3>
<div style="padding: 12px; background:rgb(255, 255, 255); border-radius: 6px; font-size: {{ $fs }}; font-family: sans-serif;">
    {!! \App\Support\SupportReportMailBodyFormatter::supportContentHtml($supportRecord, $reportMode) !!}
</div>

@if($reportMode !== 'teacher' || filled($supportRecord->TO_Depart))
<h3 style="margin-top: 1.5em; margin-bottom: 0.25em; font-size: {{ $fsHeading }};color:rgb(9, 66, 163);">본사/타 부서 공유 내용</h3>
<div style="margin: 0; padding: 12px; background:rgb(255, 255, 255); border-radius: 6px; font-size: {{ $fs }}; font-family: sans-serif; line-height: 1.5;">{!! \App\Support\SupportReportMailBodyFormatter::textWithLineBreaks($supportRecord->TO_Depart) !!}</div>
@endif

<div style="width: 100%; max-width: 100%; margin-top: 20px; padding: 8px 0 0; text-align: right;">
    <img src="{{ asset('images/logo_new2.png') }}" width="200" alt="GrapeSEED" style="display: inline-block; max-width: 220px; height: auto; margin-right: 0; border: 0; outline: none; text-decoration: none;">
</div>
</body>
</html>
