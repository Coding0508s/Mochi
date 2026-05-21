<article class="coach-support-mobile-card rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2.5">
        <div class="min-w-0">
            <button type="button"
                    class="text-left text-sm font-semibold text-blue-700 underline hover:text-blue-900"
                    wire:click.stop="openTeacherModal({{ $teacher->ID }})">
                {{ $teacher->Name }}
            </button>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                <span>{{ ltrim((string) $teacher->SK_Code, '*') }}</span>
                <span>·</span>
                <button type="button"
                        class="text-left text-blue-600 underline hover:text-blue-800"
                        wire:click.stop="openInstitutionModal('{{ $teacher->SK_Code }}')">
                    {{ $teacher->institution?->resolvedAccountName() ?: $teacher->School_Name }}
                </button>
            </div>
        </div>
        @if($teacher->Position)
            <span class="inline-flex shrink-0 items-center rounded px-2 py-0.5 text-[11px] font-medium {{ $teacher->isRetired() ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">
                {{ $teacher->Position }}
            </span>
        @endif
    </div>

    <div class="mt-2.5 grid grid-cols-2 gap-2 text-xs">
        @php
            $plan1 = \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_1st']});
            $plan2 = \App\Support\ExcelSerialDate::formatPlanMonth($teacher->{$cols['plan_2nd']});
            $done1 = \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_1st'])) ?? '-';
            $done2 = \App\Support\ExcelSerialDate::toStorageString($teacher->getRawOriginal($cols['completed_2nd'])) ?? '-';
        @endphp

        <button type="button"
                class="rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-2 text-left {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                @if($canOpenEditModal)
                    wire:click="openEditModal({{ $teacher->ID }})"
                @endif>
            <div class="text-[11px] text-blue-600">1차 계획</div>
            <div class="mt-0.5 font-medium text-gray-800">{{ $plan1 !== '' ? $plan1 : '-' }}</div>
            <div class="mt-0.5 text-gray-500">{{ $teacher->{$cols['plan_type_1st']} ?: '-' }}</div>
        </button>

        <button type="button"
                class="rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-2 text-left {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                @if($canOpenEditModal)
                    wire:click="openEditModal({{ $teacher->ID }})"
                @endif>
            <div class="text-[11px] text-blue-600">2차 계획</div>
            <div class="mt-0.5 font-medium text-gray-800">{{ $plan2 !== '' ? $plan2 : '-' }}</div>
            <div class="mt-0.5 text-gray-500">{{ $teacher->{$cols['plan_type_2nd']} ?: '-' }}</div>
        </button>

        <button type="button"
                class="rounded-lg border border-green-100 bg-green-50 px-2.5 py-2 text-left {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                @if($canOpenEditModal)
                    wire:click="openEditModal({{ $teacher->ID }})"
                @endif>
            <div class="text-[11px] text-green-700">1차 완료</div>
            <div class="mt-0.5 font-medium text-gray-800">{{ $done1 }}</div>
            <div class="mt-0.5 text-gray-500">{{ $teacher->{$cols['type_1st']} ?: '-' }}</div>
        </button>

        <button type="button"
                class="rounded-lg border border-green-100 bg-green-50 px-2.5 py-2 text-left {{ $canOpenEditModal ? 'cursor-pointer' : 'cursor-default' }}"
                @if($canOpenEditModal)
                    wire:click="openEditModal({{ $teacher->ID }})"
                @endif>
            <div class="text-[11px] text-green-700">2차 완료</div>
            <div class="mt-0.5 font-medium text-gray-800">{{ $done2 }}</div>
            <div class="mt-0.5 text-gray-500">{{ $teacher->{$cols['type_2nd']} ?: '-' }}</div>
        </button>
    </div>
</article>
