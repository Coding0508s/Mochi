<div class="mochi-page space-y-4">
    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        {{ session('error') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="mochi-summary-card">
        <div class="mt-1 flex flex-wrap items-end gap-2">
            <div class="w-full md:w-64">
                    <x-input-label for="vehicle_search" value="차량 검색" />
                    <div class="mt-1 flex items-center gap-2">
                        <select
                            id="vehicle_search"
                            wire:model.live="selectedVehicle"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">전체 차량</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->name }}">{{ $vehicle->name }}</option>
                            @endforeach
                        </select>
                        @if($selectedVehicle !== '')
                            <button
                                type="button"
                                wire:click="$set('selectedVehicle', '')"
                                class="h-[38px] shrink-0 rounded-lg border border-gray-300 px-2.5 text-xs text-gray-600 hover:bg-gray-50"
                            >
                                초기화
                            </button>
                        @endif
                    </div>
            </div>

            <div class="w-full md:w-48">
                    <x-input-label for="date_from" value="조회 시작일" />
                    <input 
                        type="date" 
                        id="date_from" 
                        wire:model.live="dateFrom" 
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
            </div>

            <div class="w-full md:w-48">
                    <x-input-label for="date_to" value="조회 종료일" />
                    <input 
                        type="date" 
                        id="date_to" 
                        wire:model.live="dateTo" 
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
            </div>

            <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-1 sm:justify-items-end">
                <button
                    type="button"
                    wire:click="exportToExcel"
                    wire:loading.attr="disabled"
                    class="inline-flex h-[42px] items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-50 hover:shadow disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="exportToExcel" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        엑셀 다운로드
                    </span>
                    <span wire:loading.inline-flex wire:target="exportToExcel" class="hidden items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        다운로드 중...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <div class="mochi-table-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3">
            <h3 class="text-base font-semibold text-[#2b78c5]">차량별 사용 내역</h3>
            <div class="text-sm text-gray-600">
                총 {{ number_format($summary['total_count']) }}건 ∙ 
                {{ number_format($summary['total_distance']) }}km ∙ 
                {{ floor($summary['total_minutes'] / 60) }}시간 {{ $summary['total_minutes'] % 60 }}분
            </div>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full border-collapse text-sm">
                <thead class="mochi-table-head text-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">사용 일시</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">사용자</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">차량명</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">운행 목적</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">도착지</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">운행 거리</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="cursor-default transition-colors hover:bg-blue-50/30">
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                @if($log->sharedSupply)
                                    <div>{{ $log->sharedSupply->starts_at->format('Y-m-d') }}</div>
                                    <div class="text-gray-500 text-xs">{{ $log->sharedSupply->starts_at->format('H:i') }} ~ {{ $log->sharedSupply->ends_at->format('H:i') }}</div>
                                @else
                                    {{ $log->driven_on ? $log->driven_on->format('Y-m-d') : '-' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->user?->name ?? '-' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $log->vehicle_name }}
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900">
                                <div class="font-medium">{{ $log->usage_purpose_name ?? '-' }}</div>
                                @if($log->remarks)
                                    @php($displayRemark = \App\Support\VehicleUsageLogRemark::forDisplay((string) $log->remarks))
                                    @if($displayRemark !== '')
                                        <div class="text-gray-500 text-xs mt-0.5 line-clamp-1" title="{{ $displayRemark }}">{{ $displayRemark }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                @php($arrivalDisplay = \App\Support\VehicleArrivalLocation::forDisplay($log->arrival_location))
                                @if($arrivalDisplay === '')
                                    @php($arrivalDisplay = \App\Support\VehicleUsageLogRemark::forDisplay((string) $log->remarks))
                                @endif
                                {{ $arrivalDisplay !== '' ? $arrivalDisplay : '-' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                {{ $log->distance ? number_format($log->distance) . ' km' : '-' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-center text-sm">
                                @if($log->odometer_after === null)
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                        운행 중
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        운행 완료
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-12 w-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.909.53l1.414 2.828a1 1 0 01.091.45V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                    </svg>
                                    <p class="text-base font-medium text-gray-900">조회된 사용 내역이 없습니다</p>
                                    <p class="text-sm text-gray-500 mt-1">다른 차량이나 기간을 선택해 보세요.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="divide-y divide-gray-200 md:hidden">
            @forelse($logs as $log)
                <div class="bg-white p-4 hover:bg-blue-50/30">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                @if($log->sharedSupply)
                                    {{ $log->sharedSupply->starts_at->format('Y-m-d H:i') }}
                                @else
                                    {{ $log->driven_on ? $log->driven_on->format('Y-m-d') : '-' }}
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $log->vehicle_name }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-gray-900">{{ $log->distance ? number_format($log->distance) . ' km' : '-' }}</div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700">
                        <span class="font-medium">{{ $log->usage_purpose_name ?? '목적 미상' }}</span>
                        @php($mobileArrivalDisplay = \App\Support\VehicleArrivalLocation::forDisplay($log->arrival_location))
                        @if($mobileArrivalDisplay === '')
                            @php($mobileArrivalDisplay = \App\Support\VehicleUsageLogRemark::forDisplay((string) $log->remarks))
                        @endif
                        @if($mobileArrivalDisplay !== '')
                            <span class="text-gray-400 mx-1">|</span>
                            <span>도착: {{ $mobileArrivalDisplay }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center text-gray-500">
                    <p class="text-sm font-medium text-gray-900">조회된 내역이 없습니다</p>
                </div>
            @endforelse
        </div>

        @if($logs->hasPages())
            <div class="border-t border-gray-100 bg-white px-4 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>