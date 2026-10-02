@props([
    'value' => null,
    'errorKey' => null,
])

<select {{ $attributes }}>
    @foreach (\App\Support\InstitutionSupportTimeSlots::optionsIncluding(is_string($value) ? $value : null) as $timeOption)
        <option value="{{ $timeOption }}">{{ $timeOption }}</option>
    @endforeach
</select>
@if (is_string($errorKey) && $errorKey !== '')
    @error($errorKey)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
@endif
