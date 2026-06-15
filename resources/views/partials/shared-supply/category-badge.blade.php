@props(['label'])

@switch($label)
    @case('차량 배차')
        <span {{ $attributes->merge(['class' => 'shrink-0 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700']) }}>차량 배차</span>
        @break
    @case('회의실')
        <span {{ $attributes->merge(['class' => 'shrink-0 rounded-md bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700']) }}>회의실</span>
        @break
    @case('출장')
        <span {{ $attributes->merge(['class' => 'shrink-0 rounded-md bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700']) }}>출장</span>
        @break
    @case('연차')
        <span {{ $attributes->merge(['class' => 'shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700']) }}>연차</span>
        @break
@endswitch
