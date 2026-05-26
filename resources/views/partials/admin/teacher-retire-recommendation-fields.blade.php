@php
    $presets = config('coach_retired_teachers.recommendation.preset_descriptions_when_yes', []);
    $defaultNo = config('coach_retired_teachers.recommendation.default_description_when_no', '해당사항없음');
@endphp

<div class="space-y-4">
    <div>
        <p class="block text-sm font-medium text-gray-700 mb-2">
            추천 여부 <span class="text-red-500">*</span>
        </p>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio"
                       wire:model.live="retireRecommendChoice"
                       value="no"
                       class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                <span class="text-sm text-gray-700">아니오</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio"
                       wire:model.live="retireRecommendChoice"
                       value="yes"
                       class="w-4 h-4 text-mochi-header border-gray-300 focus:ring-mochi-header">
                <span class="text-sm text-gray-700">예</span>
            </label>
        </div>
        @error('retireRecommendChoice')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>

    @if($retireRecommendChoice === 'yes')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                추천 사유 <span class="text-red-500">*</span>
            </label>
            <input type="text"
                   wire:model="retireRecommendDescription"
                   list="retire-recommend-presets"
                   maxlength="190"
                   placeholder="예: 높은 GrapeSEED 이해도"
                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header">
            <datalist id="retire-recommend-presets">
                @foreach($presets as $preset)
                    <option value="{{ $preset }}"></option>
                @endforeach
            </datalist>
            @error('retireRecommendDescription')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
    @else
        <p class="text-xs text-gray-500">
            비추천 시 추천 사유는 「{{ $defaultNo }}」로 저장됩니다.
        </p>
    @endif
</div>
