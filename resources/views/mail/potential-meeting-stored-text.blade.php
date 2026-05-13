{{ $meetingSavedOpening }}

[요약 — 2열]
기관명: {{ $meetingDetail->AccountName ?: '—' }}  |  작성자: {{ $submitterLabel }}
미팅일: {{ $meetingDate }}  |  시간: {{ $meetingTimeRange }}
상담 유형: {{ $meetingDetail->ConsultingType ?: '—' }}  |  가능성: {{ $meetingDetail->Possibility ?: '—' }}
담당 CO: {{ $meetingDetail->AccountManager ?: '—' }}
LittleSEED: {{ $studentCounts['ls'] ?? 0 }}  |  GrapeSEED(유): {{ $studentCounts['gs_k'] ?? 0 }}
GrapeSEED(초): {{ $studentCounts['gs_e'] ?? 0 }}  |  합계: {{ $studentCounts['total'] ?? 0 }}

--- 미팅 내용 ---
{{ $meetingDetail->Description ?: '—' }}

GrapeSEED
