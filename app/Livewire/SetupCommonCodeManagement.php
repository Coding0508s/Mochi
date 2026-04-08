<?php

namespace App\Livewire;

use App\Models\SetupCommonCode;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SetupCommonCodeManagement extends Component
{
    use WithPagination;

    public string $category = 'job_title';
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $newCode = '';
    public string $newLabel = '';
    public bool $newIsActive = true;
    public int $newSortOrder = 0;

    public int $editId = 0;
    public string $editCode = '';
    public string $editLabel = '';
    public bool $editIsActive = true;
    public int $editSortOrder = 0;

    public int $deleteId = 0;
    public string $deleteLabel = '';

    public array $categoryLabels = [
        'job_title' => '직책',
        'customer_type' => '고객유형',
        'status' => '상태값',
    ];

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->newCode = '';
        $this->newLabel = '';
        $this->newIsActive = true;
        $this->newSortOrder = 0;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function createCode(): void
    {
        $validated = $this->validate([
            'newCode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('setup_common_codes', 'code')->where('category', $this->category),
            ],
            'newLabel' => ['required', 'string', 'max:100'],
            'newSortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'newCode.required' => '코드값은 필수입니다.',
            'newCode.unique' => '같은 카테고리에 이미 존재하는 코드입니다.',
            'newLabel.required' => '표시명은 필수입니다.',
            'newSortOrder.integer' => '정렬순서는 숫자만 입력해 주세요.',
        ]);

        SetupCommonCode::query()->create([
            'category' => $this->category,
            'code' => trim($validated['newCode']),
            'label' => trim($validated['newLabel']),
            'is_active' => $this->newIsActive,
            'sort_order' => (int) $validated['newSortOrder'],
        ]);

        $this->closeCreateModal();
        session()->flash('success', '공통 코드가 생성되었습니다.');
    }

    public function openEditModal(int $id): void
    {
        $item = SetupCommonCode::query()->find($id);
        if (!$item) {
            return;
        }

        $this->editId = $item->id;
        $this->editCode = (string) $item->code;
        $this->editLabel = (string) $item->label;
        $this->editIsActive = (bool) $item->is_active;
        $this->editSortOrder = (int) $item->sort_order;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editId = 0;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function updateCode(): void
    {
        $validated = $this->validate([
            'editCode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('setup_common_codes', 'code')
                    ->where('category', $this->category)
                    ->ignore($this->editId),
            ],
            'editLabel' => ['required', 'string', 'max:100'],
            'editSortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'editCode.required' => '코드값은 필수입니다.',
            'editCode.unique' => '같은 카테고리에 이미 존재하는 코드입니다.',
            'editLabel.required' => '표시명은 필수입니다.',
            'editSortOrder.integer' => '정렬순서는 숫자만 입력해 주세요.',
        ]);

        $item = SetupCommonCode::query()->find($this->editId);
        if (!$item) {
            $this->addError('editCode', '수정할 코드를 찾을 수 없습니다.');
            return;
        }

        $item->code = trim($validated['editCode']);
        $item->label = trim($validated['editLabel']);
        $item->is_active = $this->editIsActive;
        $item->sort_order = (int) $validated['editSortOrder'];
        $item->save();

        $this->closeEditModal();
        session()->flash('success', '공통 코드가 수정되었습니다.');
    }

    public function openDeleteModal(int $id): void
    {
        $item = SetupCommonCode::query()->find($id);
        if (!$item) {
            return;
        }

        $this->deleteId = $item->id;
        $this->deleteLabel = (string) $item->label;
        $this->showDeleteModal = true;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteId = 0;
        $this->deleteLabel = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteCode(): void
    {
        $item = SetupCommonCode::query()->find($this->deleteId);
        if (!$item) {
            $this->addError('deleteId', '삭제할 코드를 찾을 수 없습니다.');
            return;
        }

        $item->delete();
        $this->closeDeleteModal();
        session()->flash('success', '공통 코드가 삭제되었습니다.');
    }

    public function render()
    {
        $items = SetupCommonCode::query()
            ->where('category', $this->category)
            ->when(trim($this->search) !== '', function ($q) {
                $keyword = preg_replace('/\s+/u', '', trim($this->search)) ?? '';
                if ($keyword === '') {
                    return;
                }

                $q->where(function ($sub) use ($keyword) {
                    $sub->whereRaw("REPLACE(code, ' ', '') like ?", ["%{$keyword}%"])
                        ->orWhereRaw("REPLACE(label, ' ', '') like ?", ["%{$keyword}%"]);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return view('livewire.setup-common-code-management', [
            'items' => $items,
            'currentCategoryLabel' => $this->categoryLabels[$this->category] ?? $this->category,
        ]);
    }
}

