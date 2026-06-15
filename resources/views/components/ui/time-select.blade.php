@props([
    'disabled' => false,
    'minuteStep' => 10,
    'hourStart' => 6,
    'hourEnd' => 24,
])

@php
    $minuteOptions = [];
    for ($minute = 0; $minute < 60; $minute += max(1, (int) $minuteStep)) {
        $minuteOptions[] = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    $hourOptions = range((int) $hourStart, (int) $hourEnd);
@endphp

<div
    {{ $attributes->except(['wire:model', 'wire:model.defer', 'wire:model.live', 'wire:model.blur']) }}
    x-data="{
        hour: '09',
        minute: '00',
        minuteOptions: @js($minuteOptions),
        init() {
            this.applyFromHidden();
            this.$refs.hidden?.addEventListener('change', () => this.applyFromHidden());
        },
        applyFromHidden() {
            const value = (this.$refs.hidden?.value || '09:00').trim();
            const [rawHour = '09', rawMinute = '00'] = value.split(':');
            this.hour = rawHour.padStart(2, '0');
            this.minute = this.normalizeMinute(rawMinute.padStart(2, '0'));
            this.sync(false);
        },
        normalizeMinute(rawMinute) {
            if (this.minuteOptions.includes(rawMinute)) {
                return rawMinute;
            }

            const numeric = Number.parseInt(rawMinute, 10);
            if (Number.isNaN(numeric)) {
                return this.minuteOptions[0] ?? '00';
            }

            const step = {{ max(1, (int) $minuteStep) }};
            const snapped = Math.min(59, Math.round(numeric / step) * step);

            return String(snapped).padStart(2, '0');
        },
        sync(dispatch = true) {
            if (this.hour === '24') {
                this.minute = '00';
            }

            if (! this.minuteOptions.includes(this.minute)) {
                this.minute = this.normalizeMinute(this.minute);
            }

            const value = `${this.hour.padStart(2, '0')}:${this.minute.padStart(2, '0')}`;

            if (this.$refs.hidden.value !== value) {
                this.$refs.hidden.value = value;
            }

            if (dispatch) {
                this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
>
    <input
        type="hidden"
        x-ref="hidden"
        {{ $attributes->whereStartsWith('wire:model') }}
        @disabled($disabled)
    >

    <div class="flex gap-2">
        <select
            x-model="hour"
            @change="sync()"
            @disabled($disabled)
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
            aria-label="시"
        >
            @foreach ($hourOptions as $hour)
                <option value="{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}">{{ $hour }}시</option>
            @endforeach
        </select>
        <select
            x-model="minute"
            @change="sync()"
            @disabled($disabled)
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
            aria-label="분"
        >
            @foreach ($minuteOptions as $minute)
                <option value="{{ $minute }}">{{ $minute }}분</option>
            @endforeach
        </select>
    </div>
</div>
