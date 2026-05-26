<div>
    <p class="text-sm font-medium text-gray-700 mb-2">수업 참여</p>
    <div class="flex flex-wrap gap-4">
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio"
                   wire:model="reinstateClassParticipation"
                   value="in"
                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
            <span>참여</span>
        </label>
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio"
                   wire:model="reinstateClassParticipation"
                   value="out"
                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
            <span>미참여</span>
        </label>
    </div>
</div>
