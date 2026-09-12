@php
    $parts = \App\Support\TeacherSupportCompletionDisplay::parts($teacher, $round, $displayYear);
@endphp
@if($parts['date'] !== '')
    <button type="button"
            class="coach-support-completed-cell coach-support-completed-open"
            wire:click.stop="openCompletedRoundSupport({{ $teacher->ID }}, {{ $round }})"
            aria-label="{{ $teacher->Name }} {{ $round }}차 지원 내용 보기">
        <span class="coach-support-completed-date">{{ $parts['date'] }}</span>
        @if($parts['type'] !== '')
            <span class="coach-support-completed-type">{{ $parts['type'] }}</span>
        @endif
        @if(($parts['extra'] ?? 0) > 0)
            <span class="coach-support-completed-extra">외 {{ $parts['extra'] }}건</span>
        @endif
    </button>
@endif
