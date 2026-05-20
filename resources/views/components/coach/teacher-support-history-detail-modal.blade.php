@props([
    'show' => false,
    'detail' => null,
])

@if($show && $detail)
    <div class="mochi-modal-overlay z-[70]"
         wire:click.self="closeTeacherSupportHistoryDetailModal">
        <div class="mochi-modal-shell max-w-3xl max-h-[85vh] z-[71] flex flex-col"
             wire:click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ $detail['title'] ?? '지원 내역 상세' }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $detail['subtitle'] ?? '' }}</p>
                </div>
                <button type="button"
                        wire:click="closeTeacherSupportHistoryDetailModal"
                        class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-4 flex-1 overflow-y-auto space-y-5">
                @foreach($detail['sections'] ?? [] as $section)
                    <div>
                        @if(!empty($section['title']))
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ $section['title'] }}</h3>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm border border-gray-100 rounded-lg p-3 bg-gray-50/50">
                            @foreach($section['fields'] ?? [] as $field)
                                <div @class(['sm:col-span-2' => str_contains((string) ($field['value'] ?? ''), "\n")])>
                                    <div class="text-xs text-gray-500 mb-0.5">{{ $field['label'] ?? '-' }}</div>
                                    <div class="font-medium text-gray-900 whitespace-pre-wrap break-words">{{ $field['value'] ?? '-' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="px-6 py-3 border-t border-gray-200 flex justify-end">
                <button type="button"
                        wire:click="closeTeacherSupportHistoryDetailModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                    닫기
                </button>
            </div>
        </div>
    </div>
@endif
