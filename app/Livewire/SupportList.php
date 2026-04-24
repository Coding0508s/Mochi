<?php

namespace App\Livewire;

use App\Models\ContractDocument;
use App\Models\Institution;
use App\Models\SupportRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SupportList extends Component
{
    use WithFileUploads;
    use WithPagination;

    // ─── 필터 상태 ────────────────────────────────────────────────
    public string $filterYear = '';   // 년도 필터

    public string $filterTr = '';   // 담당자 필터

    public string $filterSkCode = '';   // 기관 필터

    public string $search = '';   // 키워드 검색

    // ─── 보고서 작성 모달 상태 ────────────────────────────────────
    public bool $showModal = false;

    public ?int $editingId = null; // 수정 중인 레코드 ID (null이면 신규)

    // 모달 입력 필드
    public string $formSkCode = '';

    public string $formAccountName = '';

    public string $formInstitutionKeyword = ''; // 기관명 입력 검색어

    public string $formCoName = '';

    public string $formSupportDate = '';

    public string $formSupportTime = '13:00';

    public string $formSupportType = '전화';       // 지원 방법

    public string $formTarget = '';            // 참석자

    public string $formToAccount = '';            // 기관과의 소통내용

    public bool $formCompleted = false;         // 완료처리 토글

    // ─── 계약서(CO) 파일 업로드 모달 ─────────────────────────────
    public bool $showContractModal = false;

    public string $contractSkCode = '';

    public string $contractAccountName = '';

    public string $contractChangedAccountName = '';

    public string $contractBusinessNumber = '';

    public string $contractDocumentDate = '';

    public string $contractDocumentTime = '';

    public string $contractConsultant = '';

    /** @var TemporaryUploadedFile|null */
    public $contractUpload = null;

    public ?int $contractSelectedId = null;

    // ─── 유효성 검사 ─────────────────────────────────────────────
    protected array $rules = [
        'formSkCode' => 'required',
        'formSupportDate' => 'required|date',
        'formSupportTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
    ];

    protected array $messages = [
        'formSkCode.required' => '기관을 선택해 주세요.',
        'formSupportDate.required' => '지원 날짜를 입력해 주세요.',
        'formSupportDate.date' => '올바른 날짜 형식이 아닙니다.',
        'formSupportTime.required' => '지원 시간을 입력해 주세요.',
        'formSupportTime.regex' => '지원 시간은 HH:MM 형식으로 입력해 주세요.',
    ];

    // ─── 필터 변경 시 1페이지로 초기화 ───────────────────────────
    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTr(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSkCode(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ─── 계약서 업로드 모달 ───────────────────────────────────────
    public function openContractUploadModal(): void
    {
        $this->resetContractUploadForm();
        $this->contractConsultant = (string) (auth()->user()?->nameForCoReports() ?? '');
        $this->contractDocumentDate = now()->format('Y-m-d');
        $this->contractDocumentTime = now()->format('H:i');
        $this->showContractModal = true;
    }

    public function closeContractUploadModal(): void
    {
        $this->showContractModal = false;
        $this->resetContractUploadForm();
    }

    public function updatedContractSkCode(string $value): void
    {
        if (blank($value)) {
            $this->contractAccountName = '';

            return;
        }

        $inst = Institution::query()->where('SKcode', $value)->first();
        $this->contractAccountName = $inst?->AccountName ?? '';
    }

    public function selectContractDocument(int $id): void
    {
        $doc = ContractDocument::query()->find($id);
        if ($doc === null) {
            return;
        }

        $this->contractSelectedId = (int) $doc->id;
        $this->contractSkCode = (string) ($doc->sk_code ?? '');
        $this->contractAccountName = (string) ($doc->account_name ?? '');
        $this->contractChangedAccountName = (string) ($doc->changed_account_name ?? '');
        $this->contractBusinessNumber = (string) ($doc->business_number ?? '');
        $this->contractDocumentDate = $doc->document_date?->format('Y-m-d') ?? '';
        $this->contractDocumentTime = $this->normalizeTimeForInput($doc->document_time);
        $this->contractConsultant = (string) ($doc->consultant ?? '');
        $this->contractUpload = null;
        $this->resetValidation('contractUpload');
    }

    public function clearSelectedContractDocument(): void
    {
        $this->contractSelectedId = null;
        $this->contractUpload = null;
        $this->resetValidation('contractUpload');
    }

    public function saveContractDocument(): void
    {
        if ($this->contractSelectedId === null) {
            $this->uploadContractDocument();

            return;
        }

        $this->updateSelectedContractDocument();
    }

    public function uploadContractDocument(): void
    {
        $this->validate([
            'contractSkCode' => ['required', 'string', 'max:100'],
            'contractDocumentDate' => ['required', 'date'],
            'contractDocumentTime' => ['required', 'string', 'max:8'],
            'contractChangedAccountName' => ['nullable', 'string', 'max:255'],
            'contractBusinessNumber' => ['nullable', 'string', 'max:100'],
            'contractConsultant' => ['nullable', 'string', 'max:150'],
            'contractUpload' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx',
            ],
        ], [
            'contractSkCode.required' => '기관을 선택해 주세요.',
            'contractDocumentDate.required' => '날짜를 선택해 주세요.',
            'contractDocumentTime.required' => '시간을 입력해 주세요.',
            'contractUpload.required' => '업로드할 파일을 선택해 주세요.',
            'contractUpload.max' => '파일 크기는 20MB 이하여야 합니다.',
            'contractUpload.mimes' => '허용 형식: PDF, 이미지, Word, Excel',
        ]);

        $file = $this->contractUpload;
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        // TemporaryUploadedFile은 storeAs 이후 임시 파일 메타 접근이 실패할 수 있어 사전 캡처합니다.
        $originalFilename = $file->getClientOriginalName();
        $detectedMimeType = $file->getMimeType();
        $detectedSize = $file->getSize();

        $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $originalFilename) ?? 'contract';
        $storedName = Str::uuid()->toString().'_'.$safeOriginal;
        $directory = 'contract-documents/'.$this->contractSkCode;

        $path = $file->storeAs($directory, $storedName, 'local');

        if ($path === false) {
            $this->addError('contractUpload', '파일 저장에 실패했습니다.');

            return;
        }

        ContractDocument::query()->create([
            'sk_code' => $this->contractSkCode,
            'account_name' => $this->contractAccountName ?: '-',
            'changed_account_name' => $this->contractChangedAccountName ?: null,
            'business_number' => $this->contractBusinessNumber ?: null,
            'document_date' => $this->contractDocumentDate,
            'document_time' => strlen($this->contractDocumentTime) >= 5
                ? substr($this->contractDocumentTime, 0, 5).':00'
                : $this->contractDocumentTime,
            'consultant' => $this->contractConsultant ?: null,
            'original_filename' => $originalFilename,
            'stored_disk' => 'local',
            'stored_path' => $path,
            'mime_type' => $detectedMimeType,
            'size_bytes' => $detectedSize,
            'uploaded_by' => auth()->user()?->nameForCoReports(),
        ]);

        $this->contractUpload = null;
        $this->contractSelectedId = null;
        session()->flash('success', '계약서 파일이 업로드되었습니다.');
    }

    public function updateSelectedContractDocument(): void
    {
        if ($this->contractSelectedId === null) {
            return;
        }

        $this->validate([
            'contractSkCode' => ['required', 'string', 'max:100'],
            'contractDocumentDate' => ['required', 'date'],
            'contractDocumentTime' => ['required', 'string', 'max:8'],
            'contractChangedAccountName' => ['nullable', 'string', 'max:255'],
            'contractBusinessNumber' => ['nullable', 'string', 'max:100'],
            'contractConsultant' => ['nullable', 'string', 'max:150'],
            'contractUpload' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx',
            ],
        ], [
            'contractSkCode.required' => '기관을 선택해 주세요.',
            'contractDocumentDate.required' => '날짜를 선택해 주세요.',
            'contractDocumentTime.required' => '시간을 입력해 주세요.',
            'contractUpload.max' => '파일 크기는 20MB 이하여야 합니다.',
            'contractUpload.mimes' => '허용 형식: PDF, 이미지, Word, Excel',
        ]);

        $doc = ContractDocument::query()->findOrFail($this->contractSelectedId);
        $replacementFile = $this->contractUpload;

        $newStoredPath = null;
        $newOriginalFilename = (string) ($doc->original_filename ?? '');
        $newMimeType = (string) ($doc->mime_type ?? '');
        $newSizeBytes = (int) ($doc->size_bytes ?? 0);

        if ($replacementFile instanceof TemporaryUploadedFile) {
            // TemporaryUploadedFile은 storeAs 이후 임시 파일 메타 접근이 실패할 수 있어 사전 캡처합니다.
            $newOriginalFilename = $replacementFile->getClientOriginalName();
            $newMimeType = (string) $replacementFile->getMimeType();
            $newSizeBytes = (int) $replacementFile->getSize();

            $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $newOriginalFilename) ?? 'contract';
            $storedName = Str::uuid()->toString().'_'.$safeOriginal;
            $directory = 'contract-documents/'.$this->contractSkCode;
            $newStoredPath = $replacementFile->storeAs($directory, $storedName, 'local');

            if ($newStoredPath === false) {
                $this->addError('contractUpload', '파일 저장에 실패했습니다.');

                return;
            }
        }

        $oldDisk = (string) ($doc->stored_disk ?: 'local');
        $oldStoredPath = (string) ($doc->stored_path ?? '');

        try {
            $doc->update([
                'sk_code' => $this->contractSkCode,
                'account_name' => $this->contractAccountName !== '' ? $this->contractAccountName : '-',
                'changed_account_name' => $this->contractChangedAccountName !== '' ? $this->contractChangedAccountName : null,
                'business_number' => $this->contractBusinessNumber !== '' ? $this->contractBusinessNumber : null,
                'document_date' => $this->contractDocumentDate,
                'document_time' => strlen($this->contractDocumentTime) >= 5
                    ? substr($this->contractDocumentTime, 0, 5).':00'
                    : $this->contractDocumentTime,
                'consultant' => $this->contractConsultant !== '' ? $this->contractConsultant : null,
                'original_filename' => $newOriginalFilename,
                'stored_disk' => 'local',
                'stored_path' => $newStoredPath !== null ? $newStoredPath : $oldStoredPath,
                'mime_type' => $newMimeType !== '' ? $newMimeType : null,
                'size_bytes' => $newSizeBytes > 0 ? $newSizeBytes : null,
            ]);
        } catch (\Throwable $e) {
            if (is_string($newStoredPath) && $newStoredPath !== '' && Storage::disk('local')->exists($newStoredPath)) {
                Storage::disk('local')->delete($newStoredPath);
            }

            throw $e;
        }

        if (is_string($newStoredPath) && $newStoredPath !== '' && $oldStoredPath !== '' && $oldStoredPath !== $newStoredPath) {
            if (Storage::disk($oldDisk)->exists($oldStoredPath)) {
                Storage::disk($oldDisk)->delete($oldStoredPath);
            }
        }

        $this->selectContractDocument((int) $doc->id);
        session()->flash('success', '선택한 계약서 파일이 수정되었습니다.');
    }

    public function deleteSelectedContractDocument(): void
    {
        if ($this->contractSelectedId === null) {
            return;
        }

        $doc = ContractDocument::query()->findOrFail($this->contractSelectedId);
        $disk = $doc->stored_disk ?: 'local';
        if (Storage::disk($disk)->exists($doc->stored_path)) {
            Storage::disk($disk)->delete($doc->stored_path);
        }
        $doc->delete();
        $this->clearSelectedContractDocument();
        session()->flash('success', '선택한 계약서 파일을 삭제했습니다.');
    }

    private function resetContractUploadForm(): void
    {
        $this->contractSkCode = '';
        $this->contractAccountName = '';
        $this->contractChangedAccountName = '';
        $this->contractBusinessNumber = '';
        $this->contractDocumentDate = '';
        $this->contractDocumentTime = '';
        $this->contractConsultant = '';
        $this->contractUpload = null;
        $this->contractSelectedId = null;
        $this->resetValidation();
    }

    // ─── 기관 선택 시 기관명 자동 입력 ───────────────────────────
    public function updatedFormSkCode(string $value): void
    {
        if (blank($value)) {
            $this->formAccountName = '';

            return;
        }
        $inst = Institution::where('SKcode', $value)->first();
        $this->formAccountName = $inst?->AccountName ?? '';
    }

    // ─── 기관명 입력 시 검색/선택 상태 동기화 ───────────────────────
    public function updatedFormInstitutionKeyword(string $value): void
    {
        $keyword = trim($value);

        // 입력값이 비면 기관 선택 상태도 초기화
        if ($keyword === '') {
            $this->formSkCode = '';
            $this->formAccountName = '';

            return;
        }

        // 기관명을 정확히 입력한 경우 자동 선택 처리
        $inst = Institution::query()
            ->where('AccountName', $keyword)
            ->orWhere('SKcode', $keyword)
            ->first();

        if ($inst) {
            $this->formSkCode = (string) $inst->SKcode;
            $this->formAccountName = (string) $inst->AccountName;

            return;
        }

        // 아직 선택 전 상태(검색 중)로 유지
        $this->formSkCode = '';
        $this->formAccountName = '';
    }

    // ─── 자동완성 목록에서 기관 선택 ───────────────────────────────
    public function selectInstitution(string $skCode): void
    {
        $inst = Institution::query()
            ->where('SKcode', $skCode)
            ->first();

        if (! $inst) {
            return;
        }

        $this->formSkCode = (string) $inst->SKcode;
        $this->formAccountName = (string) $inst->AccountName;
        $this->formInstitutionKeyword = (string) $inst->AccountName;
    }

    // ─── 이벤트로 모달 열기 (tr onclick에서 호출) ─────────────────
    #[On('support.open-edit')]
    public function handleOpenEdit(int $id): void
    {
        $this->openEditModal($id);
    }

    // ─── 모달: 기존 레코드 수정 ───────────────────────────────────
    public function openEditModal(int $id): void
    {
        $record = SupportRecord::findOrFail($id);

        $this->editingId = $id;
        $this->formSkCode = $record->SK_Code ?? '';
        $this->formAccountName = $record->Account_Name ?? '';
        $this->formInstitutionKeyword = $record->Account_Name ?? '';
        $this->formCoName = $record->TR_Name ?? (string) (auth()->user()?->nameForCoReports() ?? '');
        $this->formSupportDate = $record->Support_Date?->format('Y-m-d') ?? '';
        $this->formSupportTime = $this->normalizeTimeForInput($record->Meet_Time);
        $this->formSupportType = $record->Support_Type ?? '전화';
        $this->formTarget = $record->Target ?? '';
        $this->formToAccount = $record->TO_Account ?? '';
        $this->formCompleted = ! is_null($record->CompletedDate);

        $this->showModal = true;
    }

    // ─── 모달 닫기 ────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formSkCode = '';
        $this->formAccountName = '';
        $this->formInstitutionKeyword = '';
        $this->formCoName = (string) (auth()->user()?->nameForCoReports() ?? '');
        $this->formSupportDate = '';
        $this->formSupportTime = '13:00';
        $this->formSupportType = '전화';
        $this->formTarget = '';
        $this->formToAccount = '';
        $this->formCompleted = false;
        $this->resetValidation();
    }

    // ─── 저장 (수정 전용) ────────────────────────────────────────
    public function save(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->formSupportTime = $this->normalizeTimeForInput($this->formSupportTime);
        $this->validate();

        DB::transaction(function (): void {
            $data = [
                'Year' => (int) date('Y', strtotime($this->formSupportDate)),
                'SK_Code' => $this->formSkCode,
                'Account_Name' => $this->formAccountName,
                'TR_Name' => $this->formCoName,
                'Support_Date' => $this->formSupportDate,
                'Meet_Time' => $this->formSupportTime.':00',
                'Support_Type' => $this->formSupportType,
                'Target' => $this->formTarget,
                'TO_Account' => $this->formToAccount,
                'Status' => $this->formCompleted ? '완료' : '진행중',
                'CompletedDate' => $this->formCompleted ? now() : null,
                'CreatedDate' => now(),
            ];

            SupportRecord::where('ID', $this->editingId)->update($data);
        });
        session()->flash('success', '지원 내역이 수정되었습니다.');

        $this->closeModal();
    }

    // ─── 완료처리 토글 (모달 밖 리스트에서 바로 클릭) ────────────
    public function toggleComplete(int $id): void
    {
        $record = SupportRecord::findOrFail($id);
        $record->toggleComplete(is_null($record->CompletedDate));
    }

    // ─── 렌더링 ──────────────────────────────────────────────────
    public function render()
    {
        $records = SupportRecord::query()
            ->ofYear($this->filterYear ? (int) $this->filterYear : null)
            ->ofTr($this->filterTr)
            ->ofInstitution($this->filterSkCode)
            ->keyword($this->search)
            ->with('institution')
            ->orderBy('Support_Date', 'desc')
            ->paginate(20);

        // 필터 드롭다운용 데이터
        $years = SupportRecord::query()
            ->whereNotNull('Year')
            ->distinct()
            ->orderBy('Year', 'desc')
            ->pluck('Year');

        $trList = SupportRecord::query()
            ->whereNotNull('TR_Name')
            ->where('TR_Name', '!=', '')
            ->distinct()
            ->orderBy('TR_Name')
            ->pluck('TR_Name');

        $institutions = Institution::query()
            ->whereNotNull('SKcode')
            ->orderBy('AccountName')
            ->get(['SKcode', 'AccountName']);

        $institutionSuggestions = Institution::query()
            ->where(function ($query) {
                $keyword = trim($this->formInstitutionKeyword);
                $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';

                if ($normalizedKeyword === '') {
                    // 검색어가 없으면 결과 없음(모달이 깔끔해짐)
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                    ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
            })
            ->orderBy('AccountName')
            ->limit(8)
            ->get(['SKcode', 'AccountName']);

        $contractDocumentRows = $this->showContractModal && filled($this->contractSkCode)
            ? ContractDocument::query()
                ->where('sk_code', $this->contractSkCode)
                ->orderByDesc('id')
                ->limit(200)
                ->get()
            : collect();

        return view('livewire.support-list', [
            'records' => $records,
            'years' => $years,
            'trList' => $trList,
            'institutions' => $institutions,
            'institutionSuggestions' => $institutionSuggestions,
            'contractDocumentRows' => $contractDocumentRows,
        ]);
    }

    private function normalizeTimeForInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '13:00';
    }
}
