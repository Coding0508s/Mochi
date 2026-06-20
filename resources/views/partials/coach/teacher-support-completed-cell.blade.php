@php
    $parts = \App\Support\TeacherSupportCompletionDisplay::parts($teacher, $round, $displayYear);
@endphp
@if($parts['date'] !== '')
    <div class="coach-support-completed-cell">
        <span class="coach-support-completed-date">{{ $parts['date'] }}</span>
        @if($parts['type'] !== '')
            <span class="coach-support-completed-type">{{ $parts['type'] }}</span>
        @endif
    </div>
@endif
