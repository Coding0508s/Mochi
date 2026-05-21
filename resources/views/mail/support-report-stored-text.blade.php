{{ $reportSavedOpening }}

[요약 — 2열]
기관명: {{ $supportRecord->Account_Name ?: '—' }}  |  작성자: {{ $submitterLabel }}
지원일: {{ $supportDate }}  |  시간: {{ $meetTime }}
지원 유형: {{ $supportRecord->Support_Type ?: '—' }}  |  {{ $reportAssigneeColumnLabel }}: {{ $supportRecord->TR_Name ?: '—' }}
참석자: {{ $supportRecord->Target ?: '—' }}  |  상태: {{ $supportRecord->Status ?: '—' }}

--- 기관 이슈 및 논의 사항 ---
{{ $supportRecord->TO_Account ?: '—' }}

--- 본사/타 부서 공유 내용 ---
{{ $supportRecord->TO_Depart ?: '—' }}

GrapeSEED
