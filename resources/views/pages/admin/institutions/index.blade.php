@extends('layouts.admin')

@section('title', '기관 리스트')

@php
    $toggleDir = fn (string $col): string => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $sortUrl = fn (string $col): string => request()->fullUrlWithQuery(['sort' => $col, 'dir' => $toggleDir($col), 'page' => null]);
@endphp

@section('content')
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-mochi-title">기관 리스트</h1>
        <form method="get" action="{{ route('institutions.index') }}" class="flex flex-wrap items-center gap-3">
            @foreach (request()->except(['q', 'gubun', 'page']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <label class="sr-only" for="filter-gubun">구분</label>
            <select
                id="filter-gubun"
                name="gubun"
                class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-mochi-header focus:outline-none focus:ring-1 focus:ring-mochi-header"
                onchange="this.form.submit()"
            >
                <option value="">전체</option>
                @foreach ($gubunOptions as $opt)
                    <option value="{{ $opt }}" @selected($gubun === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="filter-q">검색</label>
            <input
                id="filter-q"
                type="search"
                name="q"
                value="{{ $q }}"
                placeholder="기관명, SK코드, 주소 검색"
                class="min-w-[12rem] flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-mochi-header focus:outline-none focus:ring-1 focus:ring-mochi-header sm:max-w-xs"
            />
            <button type="submit" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-300">
                검색
            </button>
        </form>
        <a
            href="{{ route('institutions.export', request()->query()) }}"
            class="inline-flex items-center justify-center rounded border border-sky-300 bg-mochi-excel px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:brightness-95"
        >
            Excel
        </a>
    </div>

    <div class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
        <div class="max-h-[calc(100vh-14rem)] overflow-auto">
            <table class="min-w-full border-collapse text-left text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-mochi-table-header text-white">
                        <th class="whitespace-nowrap px-2 py-2.5 text-center font-semibold">#</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">
                            <a href="{{ $sortUrl('SKcode') }}" class="inline-flex items-center gap-1 hover:underline">
                                SKcode
                                <span class="text-[10px] opacity-80">↕</span>
                            </a>
                        </th>
                        <th class="min-w-[8rem] px-2 py-2.5 font-semibold">
                            <a href="{{ $sortUrl('AccountName') }}" class="inline-flex items-center gap-1 hover:underline">
                                기관명
                                <span class="text-[10px] opacity-80">↕</span>
                            </a>
                        </th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">CO</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">TR</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">CS</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">Type</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">
                            <a href="{{ $sortUrl('Gubun') }}" class="inline-flex items-center gap-1 hover:underline">
                                구분
                                <span class="text-[10px] opacity-80">↕</span>
                            </a>
                        </th>
                        <th class="whitespace-nowrap px-2 py-2.5 text-right font-semibold">기존원</th>
                        <th class="whitespace-nowrap px-2 py-2.5 text-right font-semibold">인원차</th>
                        <th class="whitespace-nowrap px-2 py-2.5 font-semibold">
                            <a href="{{ $sortUrl('Phone') }}" class="inline-flex items-center gap-1 hover:underline">
                                기관연락처
                                <span class="text-[10px] opacity-80">↕</span>
                            </a>
                        </th>
                        <th class="min-w-[12rem] px-2 py-2.5 font-semibold">주소</th>
                        <th class="min-w-[8rem] px-2 py-2.5 font-semibold">
                            <a href="{{ $sortUrl('EnglishName') }}" class="inline-flex items-center gap-1 hover:underline">
                                평가기관명
                                <span class="text-[10px] opacity-80">↕</span>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($institutions as $i => $row)
                        @php $info = $row->accountInfo; @endphp
                        <tr class="@if($loop->even) bg-gray-50/80 @else bg-white @endif border-b border-gray-100 hover:bg-sky-50/40">
                            <td class="px-2 py-2 text-center text-gray-500">{{ $institutions->firstItem() + $i }}</td>
                            <td class="px-2 py-2 font-mono text-xs text-gray-800">{{ $row->SKcode }}</td>
                            <td class="px-2 py-2">
                                <a href="#" class="font-medium text-mochi-header hover:underline">{{ $row->AccountName }}</a>
                            </td>
                            <td class="px-2 py-2 text-gray-700">{{ $info?->CO ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-700">{{ $info?->TR ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-700">{{ $info?->CS ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ $info?->Customer_Type ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ $row->Gubun ?? '—' }}</td>
                            <td class="px-2 py-2 text-right text-gray-500">—</td>
                            <td class="px-2 py-2 text-right text-gray-500">—</td>
                            <td class="px-2 py-2 whitespace-nowrap text-gray-700">{{ $row->Phone ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ $row->Address ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ $row->EnglishName ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-4 py-12 text-center text-gray-500">
                                표시할 기관이 없습니다. 시드 데이터가 필요하면 <code class="rounded bg-gray-100 px-1">php artisan db:seed --class=InstitutionDemoSeeder</code> 를 실행하세요.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($institutions->hasPages())
        <div class="mt-4 flex justify-end">
            {{ $institutions->links() }}
        </div>
    @endif
@endsection
