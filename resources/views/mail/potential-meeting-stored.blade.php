@php
    $fontPx = (int) config('support_report_mail.email_font_size_px', 14);
    $fs = $fontPx.'px';
    $fsHeading = ($fontPx + 3).'px';
    $wrap = 'word-break: break-word; overflow-wrap: anywhere;';
    $cellLabel = 'padding: 8px 10px; font-size: '.$fs.'; font-weight: bold; vertical-align: top; white-space: nowrap; background: #f3f4f6; border: 1px solid #e5e7eb;';
    $cellValue = 'padding: 8px 10px; font-size: '.$fs.'; vertical-align: top; border: 1px solid #e5e7eb; '.$wrap;
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>잠재기관 미팅 내역</title>
</head>
<body style="font-family: sans-serif; line-height: 1.5; color: #111; font-size: {{ $fontPx }}px;">
<p style="margin-bottom: 12px; font-size:18px; font-weight: bold;color:#590091">{{ $meetingSavedOpening }}</p>

<table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 720px; table-layout: fixed; font-size: {{ $fontPx }}px;">
    <colgroup>
        <col style="width: 13%;">
        <col style="width: 37%;">
        <col style="width: 13%;">
        <col style="width: 37%;">
    </colgroup>
    <tr>
        <td style="{{ $cellLabel }}">기관명</td>
        <td style="{{ $cellValue }}">{{ $meetingDetail->AccountName ?: '—' }}</td>
        <td style="{{ $cellLabel }}">작성자</td>
        <td style="{{ $cellValue }}">{{ $submitterLabel }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">미팅일</td>
        <td style="{{ $cellValue }}">{{ $meetingDate }}</td>
        <td style="{{ $cellLabel }}">시간</td>
        <td style="{{ $cellValue }}">{{ $meetingTimeRange }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">상담 유형</td>
        <td style="{{ $cellValue }}">{{ $meetingDetail->ConsultingType ?: '—' }}</td>
        <td style="{{ $cellLabel }}">가능성</td>
        <td style="{{ $cellValue }}">{{ $meetingDetail->Possibility ?: '—' }}</td>
    </tr>
   <!--  <tr>
        <td style="{{ $cellLabel }}">담당 CO</td>
        <td style="{{ $cellValue }}" colspan="3">{{ $meetingDetail->AccountManager ?: '—' }}</td>
    </tr> -->
    <tr>
        <td style="{{ $cellLabel }}">LittleSEED</td>
        <td style="{{ $cellValue }}">{{ $studentCounts['ls'] ?? 0 }}</td>
        <td style="{{ $cellLabel }}">GS(유치부)</td>
        <td style="{{ $cellValue }}">{{ $studentCounts['gs_k'] ?? 0 }}</td>
    </tr>
    <tr>
        <td style="{{ $cellLabel }}">GS(초등)</td>
        <td style="{{ $cellValue }}">{{ $studentCounts['gs_e'] ?? 0 }}</td>
        <td style="{{ $cellLabel }}">합계</td>
        <td style="{{ $cellValue }}">{{ $studentCounts['total'] ?? 0 }}</td>
    </tr>
</table>

<h3 style="margin-top: 1.5em; margin-bottom: 0.25em; font-size: {{ $fsHeading }};color:rgb(9, 66, 163);">미팅 내용</h3>
<pre style="white-space: pre-wrap; margin: 0; padding: 12px; background:rgb(255, 255, 255); border-radius: 6px; font-size: {{ $fs }}; font-family: sans-serif;">{{ $meetingDetail->Description ?: '—' }}</pre>

<div style="width: 100%; max-width: 100%; margin-top: 20px; padding: 8px 0 0; text-align: right;">
    <img src="{{ asset('images/logo_new2.png') }}" width="200" alt="GrapeSEED" style="display: inline-block; max-width: 220px; height: auto; margin-right: 0; border: 0; outline: none; text-decoration: none;">
</div>
</body>
</html>
