@php
    $editDetailCoreFields = $this->canEditInstitutionDetailCore();
    $editDetailCoField = $this->canEditInstitutionDetailCo();
    $editDetailTrField = $this->canEditInstitutionDetailTr();
    $editDetailCsField = $this->canEditInstitutionDetailCs();
@endphp

<div class="col-span-2 border border-gray-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-100">
            <tr>
                <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">SKcode</th>
                <td class="px-3 py-2 font-mono text-sm text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailSkCode"
                               class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailSkCode')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        <span class="font-semibold">{{ $selectedInstitution['skcode'] ?? '-' }}</span>
                    @endif
                </td>
                <th class="w-28 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailInstitutionName"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailInstitutionName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['name'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">영문명</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailEnglishName"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailEnglishName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['english_name'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">포털 표시명</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailPortalName"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailPortalName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['portal_name'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">Portal Campus ID</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900 font-mono text-sm">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailPortalCampusId"
                               class="w-full py-1.5 px-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailPortalCampusId')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['portal_campus_id'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">사업자/기관번호</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailAccountNo"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailAccountNo')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['account_no'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">구분</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailGubun" list="institution-detail-gubun-options"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        <datalist id="institution-detail-gubun-options">
                            @foreach($gubunList as $gubunOption)
                                <option value="{{ $gubunOption }}"></option>
                            @endforeach
                        </datalist>
                        @error('editDetailGubun')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['gubun'] ?? '-' }}
                    @endif
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">고객유형</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <select wire:model.defer="editCustomerType"
                                class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            <option value="">선택</option>
                            @foreach($customerTypeOptions as $typeOption)
                                <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                            @endforeach
                        </select>
                        @error('editCustomerType')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['customer_type'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">GS Number</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editGsNo" placeholder="GS Number 입력"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editGsNo')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['gs_no'] ?? '-' }}
                    @endif
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CO</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoField)
                        <select wire:model.defer="editDetailCo"
                                class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            <option value="">미지정</option>
                            @foreach($coManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                        @error('editDetailCo')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['co'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 Coach</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailTrField)
                        <select wire:model.defer="editDetailTr"
                                class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            <option value="">미지정</option>
                            @foreach($trManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                        @error('editDetailTr')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['tr'] ?? '-' }}
                    @endif
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당 CS</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCsField)
                        <select wire:model.defer="editDetailCs"
                                class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header">
                            <option value="">미지정</option>
                            @foreach($csManagerOptions as $manager)
                                <option value="{{ $manager }}">{{ $manager }}</option>
                            @endforeach
                        </select>
                        @error('editDetailCs')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['cs'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장명</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailDirector"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailDirector')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['director'] ?? '-' }}
                    @endif
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">대표전화</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailPhone"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailPhone')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['phone'] ?? '-' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">직통 연락처</th>
                <td class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <input type="text" wire:model.defer="editDetailAccountTel"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header" />
                        @error('editDetailAccountTel')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['account_tel'] ?? '-' }}
                    @endif
                </td>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">최근 지원일</th>
                <td class="px-3 py-2 font-medium text-gray-500">
                    {{ ($selectedInstitution['latest_support_date'] ?? null) ? substr((string) $selectedInstitution['latest_support_date'], 0, 10) : '-' }}
                    <p class="mt-1 text-[11px] text-gray-400">지원 이력에서 자동 집계됩니다.</p>
                </td>
            </tr>
            <tr>
                <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                <td colspan="3" class="px-3 py-2 font-medium text-gray-900">
                    @if($editDetailCoreFields)
                        <textarea wire:model.defer="editDetailAddress" rows="2"
                                  class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-mochi-header"></textarea>
                        @error('editDetailAddress')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @else
                        {{ $selectedInstitution['address'] ?? '-' }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
