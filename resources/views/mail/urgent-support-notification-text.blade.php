[긴급] 기관 지원 보고서 알림

기관명: {{ $supportRecord->Account_Name ?: '—' }}
SK 코드: {{ $supportRecord->SK_Code ?: '—' }}
작성자: {{ $senderName }}
지원일/시간: {{ $supportDate }} {{ $meetTime }}
지원 유형: {{ $supportRecord->Support_Type ?: '—' }}

@if(filled($supportRecord->TO_Account))
기관과의 소통내용:
{{ $supportRecord->TO_Account }}

@endif
보고서 확인: {{ $reportListUrl }}
