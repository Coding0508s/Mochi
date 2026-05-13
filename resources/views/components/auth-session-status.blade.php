@props(['status'])

@if ($status)
    <div {{ $attributes->merge([
        'class' => 'font-medium text-sm text-green-600',
        'data-mochi-flash-dismiss' => '3000',
        'role' => 'status',
    ]) }}>
        {{ $status }}
    </div>
@endif
