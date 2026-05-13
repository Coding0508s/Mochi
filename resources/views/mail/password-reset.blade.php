@php
    $fontPx = 14;
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('password_reset_mail.subject') }}</title>
</head>
<body style="font-family: sans-serif; line-height: 1.2; color: #111; font-size: {{ $fontPx }}px; margin: 0; padding: 24px;">
<div style="max-width: 600px; margin: 0 auto;">
    <p style="margin:0 0 16px; font-size: 16px;">
        <strong>안녕하세요.</strong>
        {{ $recipientName }}님,<br> 
        <p><strong>{{ $appName }}</strong> 계정의 비밀번호 재설정 메일을 보내드립니다.<br>
        <strong style="color: #590091;">아래 버튼을 눌러 새 비밀번호를 설정해 주세요.</strong></p>
    </p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 24px; background: #590091; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">{{ $actionLabel }}</a>
    </p>
    <p style="margin: 0 0 12px; color: #374151;">
        이 링크는 <strong>{{ $expireMinutes }}분</strong> 동안만 유효합니다.
    </p>
    <p style="margin: 0 0 8px; color: #374151;">
        버튼이 동작하지 않으면 아래 주소를 브라우저에 복사해 접속해 주세요.
    </p>
    <p style="margin: 0 0 24px; word-break: break-all; font-size: 13px; color: #4b5563;">{{ $resetUrl }}</p>
    <p style="margin: 0 0 24px; color: #6b7280;">
        본인이 요청한 적이 없다면 이 메일은 무시하셔도 되며 비밀번호는 변경되지 않습니다.
    </p>
    <div style="margin-top: 28px; padding-top: 16px; text-align: right; border-top: 1px solid #e5e7eb;">
        <img src="{{ asset('images/logo_new2.png') }}" width="200" alt="{{ $appName }}" style="display: inline-block; max-width: 220px; height: auto; border: 0; outline: none; text-decoration: none;">
    </div>
</div>
</body>
</html>
