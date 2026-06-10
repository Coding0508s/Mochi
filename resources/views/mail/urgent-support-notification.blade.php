<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>[긴급] 기관 지원 보고서 알림</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;color:#111827;font-family:Arial,'Apple SD Gothic Neo','Noto Sans KR',sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:16px 20px;background:#fff7ed;border-bottom:1px solid #fed7aa;">
            <div style="font-size:18px;font-weight:700;color:#9a3412;">[긴급] 기관 지원 보고서 알림</div>
            <div style="margin-top:4px;font-size:13px;color:#7c2d12;">기관 담당자에게 즉시 확인이 필요한 보고서가 등록되었습니다.</div>
        </td>
    </tr>
    <tr>
        <td style="padding:20px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="width:150px;padding:8px 0;color:#6b7280;">기관명</td>
                    <td style="padding:8px 0;color:#111827;font-weight:600;">{{ $supportRecord->Account_Name ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">SK 코드</td>
                    <td style="padding:8px 0;color:#111827;">{{ $supportRecord->SK_Code ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">작성자</td>
                    <td style="padding:8px 0;color:#111827;">{{ $senderName }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">지원일/시간</td>
                    <td style="padding:8px 0;color:#111827;">{{ $supportDate }} {{ $meetTime }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">지원 유형</td>
                    <td style="padding:8px 0;color:#111827;">{{ $supportRecord->Support_Type ?: '—' }}</td>
                </tr>
            </table>

            @if(filled($supportRecord->TO_Account))
                <div style="margin-top:16px;padding:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;">
                    <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:6px;">기관과의 소통내용</div>
                    <div style="font-size:13px;line-height:1.5;color:#78350f;white-space:pre-line;">{{ $supportRecord->TO_Account }}</div>
                </div>
            @endif

            <div style="margin-top:22px;">
                <a href="{{ $reportListUrl }}" style="display:inline-block;padding:10px 14px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:600;">
                    보고서 확인하기
                </a>
            </div>

            <div style="width:100%;max-width:100%;margin-top:20px;padding:8px 0 0;text-align:right;">
                <img src="{{ asset('images/logo_new2.png') }}" width="200" alt="GrapeSEED" style="display:inline-block;max-width:220px;height:auto;border:0;outline:none;text-decoration:none;">
            </div>
        </td>
    </tr>
</table>
</body>
</html>
