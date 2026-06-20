<div class="col-span-2 border border-gray-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-100">
            <tr>
                <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">SKcode</th>
                <td class="px-3 py-2 font-mono text-sm text-gray-900">
                    <span class="font-semibold">{{ $selectedInstitution['skcode'] ?? '-' }}</span>
                </td>
                <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">영문명</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['english_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">포털 표시명</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['portal_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">Portal Campus ID</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900 font-mono text-sm">{{ $selectedInstitution['portal_campus_id'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">사업자/기관번호</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['account_no'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">구분</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['gubun'] ?? '-' }}</td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">고객유형</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['customer_type'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GS Number</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['gs_no'] ?? '-' }}</td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CO</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    <div>{{ $selectedInstitution['co'] ?? '-' }}</div>
                    <p class="mt-1 text-[11px] text-gray-400">
                        최근 변경
                        @if(! empty($selectedInstitution['co_changed_at']))
                            {{ $selectedInstitution['co_changed_at'] }}
                            · {{ $selectedInstitution['co_changed_by'] ?? 'Internal Update' }}
                        @else
                            -
                        @endif
                    </p>
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    <div>{{ $selectedInstitution['tr'] ?? '-' }}</div>
                    <p class="mt-1 text-[11px] text-gray-400">
                        최근 변경
                        @if(! empty($selectedInstitution['tr_changed_at']))
                            {{ $selectedInstitution['tr_changed_at'] }}
                            · {{ $selectedInstitution['tr_changed_by'] ?? 'Internal Update' }}
                        @else
                            -
                        @endif
                    </p>
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CS</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    <div>{{ $selectedInstitution['cs'] ?? '-' }}</div>
                    <p class="mt-1 text-[11px] text-gray-400">
                        최근 변경
                        @if(! empty($selectedInstitution['cs_changed_at']))
                            {{ $selectedInstitution['cs_changed_at'] }}
                            · {{ $selectedInstitution['cs_changed_by'] ?? 'Internal Update' }}
                        @else
                            -
                        @endif
                    </p>
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장명</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['director'] ?? '-' }}</td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">대표전화</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['phone'] ?? '-' }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직통 연락처</th>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['account_tel'] ?? '-' }}</td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">최근 지원일</th>
                <td class="px-3 py-2 font-medium text-gray-500">
                    {{ $selectedInstitution['latest_support_date'] ? substr((string) $selectedInstitution['latest_support_date'], 0, 10) : '-' }}
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedInstitution['address'] ?? '-' }}</td>
            </tr>
        </tbody>
    </table>
</div>
