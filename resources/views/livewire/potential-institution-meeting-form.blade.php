<div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-3">
    <p class="text-xs text-gray-600 leading-relaxed">
        정식 기관(계약·SK 발급) 이후에는 「지원 보고서 작성」에서 기록할 수 있습니다. 해당 화면에서 저장하면 미팅 이력에 자동으로 한 줄 더 생길 수 있어, 중복 입력에 유의해 주세요.
    </p>
    @error('meetingForm')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    <form wire:submit="save" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="pim-meeting-date" class="block text-xs font-medium text-gray-600 mb-1">미팅일 <span class="text-red-500">*</span></label>
                <input id="pim-meeting-date" type="date" wire:model="meetingDate"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                @error('meetingDate') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pim-account-manager" class="block text-xs font-medium text-gray-600 mb-1">담당자</label>
                <input id="pim-account-manager" type="text" wire:model="accountManager" maxlength="100"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                @error('accountManager') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pim-time-start" class="block text-xs font-medium text-gray-600 mb-1">시작 시간</label>
                <input id="pim-time-start" type="time" wire:model="meetingTime"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                @error('meetingTime') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pim-time-end" class="block text-xs font-medium text-gray-600 mb-1">종료 시간</label>
                <input id="pim-time-end" type="time" wire:model="meetingTimeEnd"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                @error('meetingTimeEnd') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="pim-consulting" class="block text-xs font-medium text-gray-600 mb-1">컨설팅 유형 <span class="text-red-500">*</span></label>
            <input id="pim-consulting" type="text" wire:model="consultingType" maxlength="100"
                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            @error('consultingType') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="pim-possibility" class="block text-xs font-medium text-gray-600 mb-1">가능성</label>
            <input id="pim-possibility" type="text" wire:model="possibility" maxlength="20"
                   class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="A–D 등" />
            @error('possibility') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="pim-description" class="block text-xs font-medium text-gray-600 mb-1">내용</label>
            <textarea id="pim-description" wire:model="description" rows="3" maxlength="2000"
                      class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            @error('description') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer">
                미팅 저장
            </button>
        </div>
    </form>
</div>
