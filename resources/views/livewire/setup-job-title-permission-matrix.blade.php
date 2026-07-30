<div class="mochi-page">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" data-mochi-flash-dismiss="3000" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">직책 권한 매트릭스</h3>
                <p class="mt-1 text-xs text-gray-500">
                    활성 직책(공통코드)별 기능 권한을 설정합니다. 저장 시 해당 직책 직원의 연동 계정에 반영됩니다.
                </p>
            </div>

            @if($canManage)
                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="py-2 px-4 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 cursor-pointer">
                    <span wire:loading.remove wire:target="save">저장</span>
                    <span wire:loading wire:target="save">저장 중…</span>
                </button>
            @endif
        </div>
    </div>

    <div class="mochi-table-card">
        @if($rows === [])
            <div class="px-5 py-8 text-center text-sm text-gray-500">
                활성 직책 공통코드가 없습니다. Setup → 공통코드에서 직책을 먼저 등록해 주세요.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="mochi-table-head">
                    <tr class="text-gray-700">
                        <th class="px-3 py-2 text-left text-xs font-semibold sticky left-0 bg-gray-50 z-10">직책</th>
                        @foreach($flagLabels as $label)
                            <th class="px-3 py-2 text-center text-xs font-semibold">{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $jobCode => $row)
                        <tr wire:key="job-perm-{{ $jobCode }}" class="mochi-table-row-hover transition-colors">
                            <td class="px-3 py-2 sticky left-0 bg-white z-10">
                                <div class="font-medium text-gray-900">{{ $row['label'] }}</div>
                                <div class="text-xs text-gray-500">{{ $jobCode }}</div>
                            </td>
                            @foreach(array_keys($flagLabels) as $column)
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox"
                                           wire:model="rows.{{ $jobCode }}.{{ $column }}"
                                           @disabled(! $canManage)
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @unless($canManage)
                <p class="px-5 py-3 text-xs text-gray-500 border-t border-gray-100">
                    조회 전용 권한입니다. 변경하려면 Setup 관리 권한이 필요합니다.
                </p>
            @endunless
        @endif
    </div>
</div>
