@props([
    'wireModel',
    'id',
    'errorKey' => null,
    'ecountProductOptions' => [],
    'currentValue' => '',
    'inputClass' => 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header',
])

@if(count($ecountProductOptions) > 0)
    @php
        $optionValues = collect($ecountProductOptions)->pluck('value');
        $optionLabels = collect($ecountProductOptions)->pluck('label');
        $showLegacy = filled($currentValue)
            && ! $optionValues->contains($currentValue)
            && ! $optionLabels->contains($currentValue);
    @endphp

    <div class="relative"
         x-data="{
            options: @js($ecountProductOptions),
            value: @entangle($wireModel),
            query: '',
            open: false,
            legacyValue: @js($showLegacy ? $currentValue : null),
            init() {
                this.syncQueryFromValue();
                this.$watch('value', () => this.syncQueryFromValue());
            },
            syncQueryFromValue() {
                if (! this.value) {
                    if (! this.open) {
                        this.query = '';
                    }

                    return;
                }

                const match = this.options.find((option) => option.value === this.value);
                if (match) {
                    this.query = match.label;

                    return;
                }

                if (this.legacyValue && this.value === this.legacyValue) {
                    this.query = this.legacyValue;
                }
            },
            get filteredOptions() {
                const keyword = this.query.trim().toLowerCase();
                if (keyword === '') {
                    return this.options.slice(0, 30);
                }

                return this.options.filter((option) => {
                    return option.label.toLowerCase().includes(keyword)
                        || option.value.toLowerCase().includes(keyword);
                }).slice(0, 30);
            },
            selectOption(option) {
                this.value = option.value;
                this.query = option.label;
                this.open = false;
            },
            onInput() {
                this.open = true;

                const trimmed = this.query.trim();
                const upper = trimmed.toUpperCase();
                const exact = this.options.find((option) => {
                    return option.label === trimmed || option.value === upper;
                });

                if (exact) {
                    this.value = exact.value;

                    return;
                }

                const selected = this.options.find((option) => option.value === this.value);
                if (this.value && (! selected || selected.label !== this.query)) {
                    this.value = '';
                }
            },
         }"
         @click.outside="open = false">
        <input id="{{ $id }}"
               type="text"
               x-model="query"
               @input="onInput()"
               @focus="open = true"
               placeholder="품목명 또는 코드로 검색"
               autocomplete="off"
               class="{{ $inputClass }}">

        <div x-show="open && filteredOptions.length > 0"
             x-cloak
             class="absolute z-20 mt-1 max-h-44 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <template x-for="option in filteredOptions" :key="option.value">
                <button type="button"
                        class="w-full px-3 py-2 text-left text-sm transition-colors hover:bg-blue-50"
                        @click="selectOption(option)">
                    <span class="font-medium text-gray-900" x-text="option.label"></span>
                    <span class="ml-2 text-xs text-gray-500" x-text="'(' + option.value + ')'"></span>
                </button>
            </template>
        </div>

        <p x-show="open && query.trim() !== '' && filteredOptions.length === 0"
           x-cloak
           class="mt-1 text-xs text-gray-500">
            검색 결과가 없습니다.
        </p>
    </div>
@else
    <input id="{{ $id }}"
           type="text"
           wire:model="{{ $wireModel }}"
           placeholder="품목명 입력"
           class="{{ $inputClass }}">
@endif

@if($errorKey)
    @error($errorKey) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
@endif
