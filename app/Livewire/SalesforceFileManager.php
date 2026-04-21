<?php

namespace App\Livewire;

use App\Models\ContractDocument;
use App\Models\SalesforceAccount;
use App\Models\SalesforceFile;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SalesforceFileManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public int $masterAmount = 15;

    private const DETAIL_PER_PAGE = 15;

    public string $masterTab = 'accounts';

    public string $masterSearch = '';

    public string $detailSearch = '';

    public ?int $selectedAccountId = null;

    public ?int $selectedUnlinkedSfId = null;

    public bool $showPreviewModal = false;

    public ?int $previewDocId = null;

    public string $previewFileName = '';

    public bool $showEditModal = false;

    public ?int $editingDocumentId = null;

    public string $editSkCode = '';

    public string $editAccountName = '';

    public string $editChangedAccountName = '';

    public string $editBusinessNumber = '';

    public string $editDocumentDate = '';

    public string $editDocumentTime = '';

    public string $editConsultant = '';

    public string $editOriginalFilename = '';

    /** @var TemporaryUploadedFile|null */
    public $editReplacementUpload = null;

    public function mount(): void
    {
        if (! in_array($this->masterTab, ['accounts', 'unlinked'], true)) {
            $this->masterTab = 'accounts';
        }
    }

    public function loadMoreMaster(): void
    {
        $this->masterAmount += 15;
    }

    public function updatingMasterSearch(): void
    {
        $this->masterAmount = 15;
        $this->resetPage(pageName: 'detailPage');
    }

    public function updatingDetailSearch(): void
    {
        $this->closePreviewModal();
        $this->closeDocumentEditModal();
        $this->resetPage(pageName: 'detailPage');
    }

    public function switchMasterTab(string $tab): void
    {
        if (! in_array($tab, ['accounts', 'unlinked'], true)) {
            return;
        }

        $this->masterTab = $tab;
        $this->detailSearch = '';
        $this->masterAmount = 15;
        $this->closePreviewModal();
        $this->closeDocumentEditModal();
        $this->resetPage(pageName: 'detailPage');

        if ($tab === 'accounts') {
            $this->selectedUnlinkedSfId = null;
        } else {
            $this->selectedAccountId = null;
        }
    }

    public function selectAccount(int $accountId): void
    {
        $this->selectedAccountId = $accountId;
        $this->closePreviewModal();
        $this->closeDocumentEditModal();
        $this->resetPage(pageName: 'detailPage');
    }

    public function selectUnlinkedSfFile(int $sfFileId): void
    {
        $this->selectedUnlinkedSfId = $sfFileId;
        $this->closePreviewModal();
        $this->closeDocumentEditModal();
        $this->resetPage(pageName: 'detailPage');
    }

    public function openPreviewModal(int $docId): void
    {
        if (! $this->canQueryContractDocuments()) {
            return;
        }

        $document = ContractDocument::query()
            ->whereKey($docId)
            ->first(['id', 'original_filename', 'stored_disk', 'stored_path']);

        if ($document === null) {
            return;
        }

        $disk = $document->stored_disk ?: 'local';
        if (! filled($document->stored_path) || ! Storage::disk($disk)->exists($document->stored_path)) {
            return;
        }

        $this->previewDocId = (int) $document->id;
        $this->previewFileName = (string) ($document->original_filename ?? '');
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewDocId = null;
        $this->previewFileName = '';
    }

    public function openDocumentEditModal(int $docId): void
    {
        if (! $this->canQueryContractDocuments()) {
            return;
        }

        $document = ContractDocument::query()
            ->whereKey($docId)
            ->first([
                'id',
                'sk_code',
                'account_name',
                'changed_account_name',
                'business_number',
                'document_date',
                'document_time',
                'consultant',
                'original_filename',
            ]);

        if ($document === null) {
            return;
        }

        $this->editingDocumentId = (int) $document->id;
        $this->editSkCode = (string) ($document->sk_code ?? '');
        $this->editAccountName = (string) ($document->account_name ?? '');
        $this->editChangedAccountName = (string) ($document->changed_account_name ?? '');
        $this->editBusinessNumber = (string) ($document->business_number ?? '');
        $this->editDocumentDate = $document->document_date?->format('Y-m-d') ?? '';
        $this->editDocumentTime = $this->normalizeTimeForInput((string) ($document->document_time ?? ''));
        $this->editConsultant = (string) ($document->consultant ?? '');
        $this->editOriginalFilename = (string) ($document->original_filename ?? '');
        $this->editReplacementUpload = null;
        $this->showEditModal = true;
        $this->resetValidation();
    }

    public function closeDocumentEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingDocumentId = null;
        $this->editSkCode = '';
        $this->editAccountName = '';
        $this->editChangedAccountName = '';
        $this->editBusinessNumber = '';
        $this->editDocumentDate = '';
        $this->editDocumentTime = '';
        $this->editConsultant = '';
        $this->editOriginalFilename = '';
        $this->editReplacementUpload = null;
        $this->resetValidation();
    }

    public function saveDocumentEdit(): void
    {
        if ($this->editingDocumentId === null) {
            return;
        }

        $this->validate([
            'editSkCode' => ['required', 'string', 'max:100'],
            'editAccountName' => ['nullable', 'string', 'max:255'],
            'editChangedAccountName' => ['nullable', 'string', 'max:255'],
            'editBusinessNumber' => ['nullable', 'string', 'max:100'],
            'editDocumentDate' => ['required', 'date'],
            'editDocumentTime' => ['required', 'string', 'max:8'],
            'editConsultant' => ['nullable', 'string', 'max:150'],
            'editOriginalFilename' => ['required', 'string', 'max:255'],
            'editReplacementUpload' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx',
            ],
        ], [
            'editSkCode.required' => '기관 코드를 입력해 주세요.',
            'editDocumentDate.required' => '날짜를 입력해 주세요.',
            'editDocumentTime.required' => '시간을 입력해 주세요.',
            'editOriginalFilename.required' => '파일명을 입력해 주세요.',
            'editReplacementUpload.max' => '파일 크기는 20MB 이하여야 합니다.',
            'editReplacementUpload.mimes' => '허용 형식: PDF, 이미지, Word, Excel',
        ]);

        $document = ContractDocument::query()->findOrFail($this->editingDocumentId);
        $replacement = $this->editReplacementUpload;

        $newStoredPath = null;
        $newOriginalFilename = trim($this->editOriginalFilename) !== ''
            ? trim($this->editOriginalFilename)
            : (string) ($document->original_filename ?? '');
        $newMimeType = (string) ($document->mime_type ?? '');
        $newSizeBytes = (int) ($document->size_bytes ?? 0);

        if ($replacement instanceof TemporaryUploadedFile) {
            // TemporaryUploadedFile은 storeAs 이후 임시 파일 메타 접근이 실패할 수 있어 사전 캡처합니다.
            $newOriginalFilename = $replacement->getClientOriginalName();
            $newMimeType = (string) $replacement->getMimeType();
            $newSizeBytes = (int) $replacement->getSize();

            $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $newOriginalFilename) ?? 'contract';
            $storedName = Str::uuid()->toString().'_'.$safeOriginal;
            $directory = 'contract-documents/'.$this->editSkCode;
            $newStoredPath = $replacement->storeAs($directory, $storedName, 'local');

            if ($newStoredPath === false) {
                $this->addError('editReplacementUpload', '파일 저장에 실패했습니다.');

                return;
            }
        }

        $oldDisk = (string) ($document->stored_disk ?: 'local');
        $oldStoredPath = (string) ($document->stored_path ?? '');

        try {
            $document->update([
                'sk_code' => $this->editSkCode,
                'account_name' => trim($this->editAccountName) !== '' ? trim($this->editAccountName) : '-',
                'changed_account_name' => trim($this->editChangedAccountName) !== '' ? trim($this->editChangedAccountName) : null,
                'business_number' => trim($this->editBusinessNumber) !== '' ? trim($this->editBusinessNumber) : null,
                'document_date' => $this->editDocumentDate,
                'document_time' => strlen($this->editDocumentTime) >= 5
                    ? substr($this->editDocumentTime, 0, 5).':00'
                    : $this->editDocumentTime,
                'consultant' => trim($this->editConsultant) !== '' ? trim($this->editConsultant) : null,
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

        $this->closeDocumentEditModal();
        session()->flash('success', '파일 정보가 수정되었습니다.');
    }

    public function deleteDocument(int $docId): void
    {
        if (! $this->canQueryContractDocuments()) {
            return;
        }

        $document = ContractDocument::query()->find($docId);
        if ($document === null) {
            return;
        }

        $disk = (string) ($document->stored_disk ?: 'local');
        $path = (string) ($document->stored_path ?? '');
        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $document->delete();

        if ($this->previewDocId === (int) $docId) {
            $this->closePreviewModal();
        }
        if ($this->editingDocumentId === (int) $docId) {
            $this->closeDocumentEditModal();
        }

        session()->flash('success', '선택한 파일이 삭제되었습니다.');
    }

    public function render(): View
    {
        if ($this->masterTab === 'unlinked') {
            $unlinkedRows = $this->buildUnlinkedMasterPaginator();
            $selectedUnlinked = $this->resolveSelectedUnlinkedFile($unlinkedRows);
            $detailRows = $this->buildUnlinkedDetailPaginator($selectedUnlinked);
            $accountRows = $this->emptyPaginator('accountsPage');
            $selectedAccount = null;
        } else {
            $accountRows = $this->buildAccountMasterPaginator();
            $selectedAccount = $this->resolveSelectedAccount($accountRows);
            $detailRows = $this->buildAccountDetailPaginator($selectedAccount);
            $unlinkedRows = $this->emptyPaginator('unlinkedMasterPage');
            $selectedUnlinked = null;
        }

        $docIdsToCheck = collect($detailRows->items())
            ->pluck('contract_document_id')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $fileAvailableByDocId = $this->buildFileAvailableByDocId($docIdsToCheck);

        return view('livewire.salesforce-file-manager', [
            'accountRows' => $accountRows,
            'unlinkedRows' => $unlinkedRows,
            'detailRows' => $detailRows,
            'selectedAccount' => $selectedAccount,
            'selectedUnlinked' => $selectedUnlinked,
            'fileAvailableByDocId' => $fileAvailableByDocId,
        ]);
    }

    private function buildAccountMasterPaginator(): LengthAwarePaginator
    {
        if (! $this->canQuerySfAccount()) {
            return $this->emptyPaginator('accountsPage');
        }

        $query = SalesforceAccount::query()
            ->select(['ID', 'account_ID', 'Name', 'GSKR_Contract__c', 'GSKR_Gts_Type__c'])
            ->orderByRaw("CASE WHEN Name IS NULL OR Name = '' THEN 1 ELSE 0 END")
            ->orderBy('Name')
            ->orderByRaw("CASE WHEN account_ID IS NULL OR account_ID = '' THEN 1 ELSE 0 END")
            ->orderBy('account_ID')
            ->orderByDesc('ID');

        $keyword = trim($this->masterSearch);
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('account_ID', 'like', '%'.$keyword.'%')
                    ->orWhere('Name', 'like', '%'.$keyword.'%')
                    ->orWhere('GSKR_Contract__c', 'like', '%'.$keyword.'%')
                    ->orWhere('GSKR_Gts_Type__c', 'like', '%'.$keyword.'%');
            });
        }

        return $query->paginate($this->masterAmount, ['*'], 'accountsPage', 1);
    }

    private function resolveSelectedAccount(LengthAwarePaginator $accountRows): ?SalesforceAccount
    {
        if (! $this->canQuerySfAccount()) {
            $this->selectedAccountId = null;

            return null;
        }

        if ($this->selectedAccountId !== null) {
            $selected = SalesforceAccount::query()->find($this->selectedAccountId);
            if ($selected !== null) {
                return $selected;
            }
        }

        /** @var SalesforceAccount|null $first */
        $first = $accountRows->items()[0] ?? null;
        if ($first !== null) {
            $this->selectedAccountId = (int) $first->ID;

            return $first;
        }

        $this->selectedAccountId = null;

        return null;
    }

    private function buildAccountDetailPaginator(?SalesforceAccount $selectedAccount): LengthAwarePaginator
    {
        if (! $this->canQuerySfFiles() || $selectedAccount === null || ! filled($selectedAccount->account_ID)) {
            return $this->emptyPaginator('detailPage', self::DETAIL_PER_PAGE);
        }

        [$exactContractDocIdByFileName, $contractMetaById] = $this->buildContractContextByAccount($selectedAccount);

        $page = max(1, LengthAwarePaginator::resolveCurrentPage('detailPage'));
        $offset = ($page - 1) * self::DETAIL_PER_PAGE;

        $items = [];
        $total = 0;
        $linkedDocIdsSet = [];

        foreach (
            SalesforceFile::query()
                ->where('fileName', 'like', (string) $selectedAccount->account_ID.'%')
                ->orderByDesc('ID')
                ->cursor() as $sfFile
        ) {
            $row = $this->buildAccountSfRow($sfFile, $selectedAccount, $exactContractDocIdByFileName, $contractMetaById);
            if (! $this->matchesDetailSearch($row)) {
                continue;
            }

            $docId = $row['contract_document_id'] ?? null;
            if ($docId !== null) {
                $linkedDocIdsSet[(int) $docId] = true;
            }

            if ($total >= $offset && count($items) < self::DETAIL_PER_PAGE) {
                $items[] = $row;
            }
            $total++;
        }

        foreach ($contractMetaById as $docId => $meta) {
            if (isset($linkedDocIdsSet[(int) $docId])) {
                continue;
            }

            $row = $this->buildAccountContractOnlyRow($selectedAccount, (int) $docId, $meta);
            if (! $this->matchesDetailSearch($row)) {
                continue;
            }

            if ($total >= $offset && count($items) < self::DETAIL_PER_PAGE) {
                $items[] = $row;
            }
            $total++;
        }

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: self::DETAIL_PER_PAGE,
            currentPage: $page,
            options: ['path' => request()->url(), 'pageName' => 'detailPage']
        );
    }

    /**
     * @return array{0: array<string, int>, 1: array<int, array<string, mixed>>}
     */
    private function buildContractContextByAccount(SalesforceAccount $account): array
    {
        if (! $this->canQueryContractDocuments()) {
            return [[], []];
        }

        $contractId = trim((string) ($account->GSKR_Contract__c ?? ''));
        $accountName = trim((string) ($account->Name ?? ''));

        $query = DB::table('contract_documents')
            ->select([
                'id',
                'original_filename',
                'consultant',
                'uploaded_by',
                'created_at',
            ])
            ->orderByDesc('id');

        if ($contractId === '' && $accountName === '') {
            return [[], []];
        }

        $query->where(function ($sub) use ($contractId, $accountName): void {
            if ($contractId !== '') {
                $sub->orWhere('original_filename', 'like', '%'.$contractId.'%');
            }
            if ($accountName !== '') {
                $sub->orWhere('account_name', 'like', '%'.$accountName.'%')
                    ->orWhere('original_filename', 'like', '%'.$accountName.'%');
            }
        });

        $contractCandidates = $query->get();

        $exactMap = [];
        $metaById = [];
        foreach ($contractCandidates as $doc) {
            $docId = (int) ($doc->id ?? 0);
            if ($docId <= 0) {
                continue;
            }

            $original = (string) ($doc->original_filename ?? '');
            if ($original !== '') {
                $exactKey = mb_strtolower($original);
                if (! isset($exactMap[$exactKey])) {
                    $exactMap[$exactKey] = $docId;
                }
            }

            $metaById[$docId] = [
                'original_filename' => $original,
                'consultant' => (string) ($doc->consultant ?? ''),
                'uploaded_by' => (string) ($doc->uploaded_by ?? ''),
                'created_at' => $this->formatDateTime((string) ($doc->created_at ?? '')),
                'normalized' => $this->normalizeFileNameForMatching($original),
            ];
        }

        return [$exactMap, $metaById];
    }

    /**
     * @param  array<string, int>  $exactContractDocIdByFileName
     * @param  array<int, array<string, mixed>>  $contractMetaById
     * @return array<string, mixed>
     */
    private function buildAccountSfRow(
        SalesforceFile $sfFile,
        SalesforceAccount $account,
        array $exactContractDocIdByFileName,
        array $contractMetaById
    ): array {
        $fileName = (string) ($sfFile->fileName ?? '');
        $docId = $this->resolveContractDocIdByFileName($fileName, $exactContractDocIdByFileName, $contractMetaById);
        $meta = $docId !== null ? ($contractMetaById[(int) $docId] ?? null) : null;

        return [
            'row_key' => 'sf-'.(int) $sfFile->ID,
            'source' => 'sf_file',
            'source_label' => 'SF 원본',
            'record_id' => (int) $sfFile->ID,
            'account_id' => (string) ($account->account_ID ?? ''),
            'account_name' => (string) ($account->Name ?? ''),
            'contract_id' => (string) ($account->GSKR_Contract__c ?? ''),
            'gts_type' => (string) ($account->GSKR_Gts_Type__c ?? ''),
            'file_name' => $fileName,
            'user' => (string) ($sfFile->User ?? ''),
            'created_date' => (string) ($sfFile->created_Date ?? ''),
            'consultant' => (string) ($meta['consultant'] ?? ''),
            'contract_document_id' => $docId !== null ? (int) $docId : null,
            'file_match_status' => $docId !== null ? 'linked' : 'unlinked',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function buildAccountContractOnlyRow(SalesforceAccount $account, int $docId, array $meta): array
    {
        return [
            'row_key' => 'contract-only-'.$docId,
            'source' => 'contract_only',
            'source_label' => '내부 업로드',
            'record_id' => $docId,
            'account_id' => (string) ($account->account_ID ?? ''),
            'account_name' => (string) ($account->Name ?? ''),
            'contract_id' => (string) ($account->GSKR_Contract__c ?? ''),
            'gts_type' => (string) ($account->GSKR_Gts_Type__c ?? ''),
            'file_name' => (string) ($meta['original_filename'] ?? ''),
            'user' => (string) ($meta['uploaded_by'] ?? ''),
            'created_date' => (string) ($meta['created_at'] ?? ''),
            'consultant' => (string) ($meta['consultant'] ?? ''),
            'contract_document_id' => $docId,
            'file_match_status' => 'linked',
        ];
    }

    private function buildUnlinkedMasterPaginator(): LengthAwarePaginator
    {
        if (! $this->canQuerySfFiles()) {
            return $this->emptyPaginator('unlinkedMasterPage');
        }

        $validAccountSet = $this->buildValidSfAccountIdSet();

        $limit = $this->masterAmount;
        $items = [];
        $total = 0;
        $keyword = mb_strtolower(trim($this->masterSearch));

        foreach (SalesforceFile::query()->orderByDesc('ID')->cursor() as $sfFile) {
            $fileName = (string) ($sfFile->fileName ?? '');
            $parsedAccountId = $this->extractSfAccountId($fileName);
            $status = null;
            if ($parsedAccountId === '') {
                $status = 'parse_failed';
            } elseif (! isset($validAccountSet[$this->normalizeAccountId($parsedAccountId)])) {
                $status = 'account_missing';
            }

            if ($status === null) {
                continue;
            }

            $row = [
                'id' => (int) $sfFile->ID,
                'file_name' => $fileName,
                'user' => (string) ($sfFile->User ?? ''),
                'created_date' => (string) ($sfFile->created_Date ?? ''),
                'parsed_account_id' => $parsedAccountId,
                'status' => $status,
            ];

            if (! $this->matchesUnlinkedMasterSearch($row, $keyword)) {
                continue;
            }

            if ($total < $limit) {
                $items[] = $row;
            }
            $total++;
        }

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $limit,
            currentPage: 1,
            options: ['path' => request()->url(), 'pageName' => 'unlinkedMasterPage']
        );
    }

    private function resolveSelectedUnlinkedFile(LengthAwarePaginator $unlinkedRows): ?SalesforceFile
    {
        if (! $this->canQuerySfFiles()) {
            $this->selectedUnlinkedSfId = null;

            return null;
        }

        if ($this->selectedUnlinkedSfId !== null) {
            $selected = SalesforceFile::query()->find($this->selectedUnlinkedSfId);
            if ($selected !== null && $this->isUnlinkedFile($selected)) {
                return $selected;
            }
        }

        $first = $unlinkedRows->items()[0] ?? null;
        if (is_array($first) && isset($first['id'])) {
            $this->selectedUnlinkedSfId = (int) $first['id'];

            return SalesforceFile::query()->find((int) $first['id']);
        }

        $this->selectedUnlinkedSfId = null;

        return null;
    }

    private function isUnlinkedFile(SalesforceFile $sfFile): bool
    {
        $parsed = $this->extractSfAccountId((string) ($sfFile->fileName ?? ''));
        if ($parsed === '') {
            return true;
        }

        $validAccountSet = $this->buildValidSfAccountIdSet();

        return ! isset($validAccountSet[$this->normalizeAccountId($parsed)]);
    }

    /**
     * @return array<string, bool>
     */
    private function buildValidSfAccountIdSet(): array
    {
        if (! $this->canQuerySfAccount()) {
            return [];
        }

        $set = [];
        foreach (DB::table('SF_Account')->pluck('account_ID') as $accountId) {
            $normalized = $this->normalizeAccountId((string) $accountId);
            if ($normalized !== '') {
                $set[$normalized] = true;
            }
        }

        return $set;
    }

    private function buildUnlinkedDetailPaginator(?SalesforceFile $selectedFile): LengthAwarePaginator
    {
        if (! $this->canQuerySfFiles() || $selectedFile === null) {
            return $this->emptyPaginator('detailPage', self::DETAIL_PER_PAGE);
        }

        $row = $this->buildUnlinkedDetailRow($selectedFile);
        if (! $this->matchesDetailSearch($row)) {
            return $this->emptyPaginator('detailPage', self::DETAIL_PER_PAGE);
        }

        return new LengthAwarePaginator(
            items: [$row],
            total: 1,
            perPage: self::DETAIL_PER_PAGE,
            currentPage: 1,
            options: ['path' => request()->url(), 'pageName' => 'detailPage']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnlinkedDetailRow(SalesforceFile $sfFile): array
    {
        $fileName = (string) ($sfFile->fileName ?? '');
        $parsedAccountId = $this->extractSfAccountId($fileName);
        $status = $parsedAccountId === '' ? 'parse_failed' : 'account_missing';

        $linkedDoc = $this->findLinkedContractDocumentForUnlinkedFile($fileName);

        return [
            'row_key' => 'unlinked-detail-'.(int) $sfFile->ID,
            'source' => 'sf_unlinked',
            'source_label' => '미분류 SF',
            'record_id' => (int) $sfFile->ID,
            'account_id' => $parsedAccountId,
            'account_name' => $status === 'parse_failed' ? 'ID 파싱 실패' : '계정 없음',
            'contract_id' => '',
            'gts_type' => '',
            'file_name' => $fileName,
            'user' => (string) ($sfFile->User ?? ''),
            'created_date' => (string) ($sfFile->created_Date ?? ''),
            'consultant' => (string) ($linkedDoc?->consultant ?? ''),
            'contract_document_id' => $linkedDoc?->id ? (int) $linkedDoc->id : null,
            'file_match_status' => $linkedDoc !== null ? 'linked' : 'unlinked',
        ];
    }

    private function findLinkedContractDocumentForUnlinkedFile(string $sfFileName): ?ContractDocument
    {
        if (! $this->canQueryContractDocuments() || $sfFileName === '') {
            return null;
        }

        $exact = ContractDocument::query()
            ->where('original_filename', $sfFileName)
            ->orderByDesc('id')
            ->first(['id', 'consultant', 'original_filename']);
        if ($exact !== null) {
            return $exact;
        }

        $targetNormalized = $this->normalizeFileNameForMatching($sfFileName);
        if ($targetNormalized === '') {
            return null;
        }

        $tokens = $this->extractFileSearchTokens($sfFileName);
        if ($tokens === []) {
            return null;
        }

        $candidates = ContractDocument::query()
            ->select(['id', 'consultant', 'original_filename'])
            ->where(function ($query) use ($tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhere('original_filename', 'like', '%'.$token.'%');
                }
            })
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = ContractDocument::query()
                ->select(['id', 'consultant', 'original_filename'])
                ->orderByDesc('id')
                ->limit(600)
                ->get();
        }

        $fuzzyMatched = null;
        $targetTokens = $this->extractFileSearchTokens($sfFileName);
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            $candidateNormalized = $this->normalizeFileNameForMatching((string) ($candidate->original_filename ?? ''));
            if ($candidateNormalized === '') {
                continue;
            }

            if ($candidateNormalized === $targetNormalized) {
                return $candidate;
            }

            $score = $this->calculateFilenameSimilarityScore(
                $targetNormalized,
                $targetTokens,
                $candidateNormalized,
                $this->extractFileSearchTokens((string) ($candidate->original_filename ?? ''))
            );
            if ($score > $bestScore) {
                $bestScore = $score;
                $fuzzyMatched = $candidate;
            }
        }

        return $bestScore >= 0.45 ? $fuzzyMatched : null;
    }

    /**
     * @param  array<string, int>  $exactMap
     * @param  array<int, array<string, mixed>>  $contractMetaById
     */
    private function resolveContractDocIdByFileName(string $sfFileName, array $exactMap, array $contractMetaById): ?int
    {
        if ($sfFileName === '') {
            return null;
        }

        $exactDocId = $exactMap[mb_strtolower($sfFileName)] ?? null;
        if ($exactDocId !== null) {
            return (int) $exactDocId;
        }

        $targetNormalized = $this->normalizeFileNameForMatching($sfFileName);
        if ($targetNormalized === '') {
            return null;
        }

        $targetTokens = $this->extractFileSearchTokens($sfFileName);
        $bestDocId = null;
        $bestScore = 0.0;

        foreach ($contractMetaById as $docId => $meta) {
            $candidateFilename = (string) ($meta['original_filename'] ?? '');
            if ($candidateFilename === '') {
                continue;
            }

            $candidateNormalized = (string) ($meta['normalized'] ?? $this->normalizeFileNameForMatching($candidateFilename));
            if ($candidateNormalized === '') {
                continue;
            }

            if ($candidateNormalized === $targetNormalized) {
                return (int) $docId;
            }

            $score = $this->calculateFilenameSimilarityScore(
                $targetNormalized,
                $targetTokens,
                $candidateNormalized,
                $this->extractFileSearchTokens($candidateFilename)
            );
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDocId = (int) $docId;
            }
        }

        return $bestScore >= 0.45 ? $bestDocId : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesDetailSearch(array $row): bool
    {
        $keyword = mb_strtolower(trim($this->detailSearch));
        if ($keyword === '') {
            return true;
        }

        $targets = [
            $row['file_name'] ?? '',
            $row['user'] ?? '',
            $row['created_date'] ?? '',
            $row['account_id'] ?? '',
            $row['account_name'] ?? '',
            $row['contract_id'] ?? '',
            $row['consultant'] ?? '',
            $row['gts_type'] ?? '',
            $row['source_label'] ?? '',
        ];

        foreach ($targets as $target) {
            if (str_contains(mb_strtolower((string) $target), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesUnlinkedMasterSearch(array $row, string $keyword): bool
    {
        if ($keyword === '') {
            return true;
        }

        $targets = [
            $row['file_name'] ?? '',
            $row['user'] ?? '',
            $row['created_date'] ?? '',
            $row['parsed_account_id'] ?? '',
            $row['status'] ?? '',
        ];

        foreach ($targets as $target) {
            if (str_contains(mb_strtolower((string) $target), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildFileAvailableByDocId(Collection $docIds): array
    {
        if ($docIds->isEmpty() || ! $this->canQueryContractDocuments()) {
            return [];
        }

        return ContractDocument::query()
            ->whereIn('id', $docIds->all())
            ->get(['id', 'stored_disk', 'stored_path'])
            ->mapWithKeys(function (ContractDocument $doc): array {
                $disk = $doc->stored_disk ?: 'local';
                $exists = filled($doc->stored_path) && Storage::disk($disk)->exists($doc->stored_path);

                return [(int) $doc->id => $exists];
            })
            ->all();
    }

    private function extractSfAccountId(string $fileName): string
    {
        if (! str_contains($fileName, '_')) {
            return '';
        }

        $parts = explode('_', $fileName, 2);
        $candidate = trim((string) ($parts[0] ?? ''));
        if (! preg_match('/^[a-zA-Z0-9]{15,30}$/', $candidate)) {
            return '';
        }

        return $candidate;
    }

    private function normalizeAccountId(string $accountId): string
    {
        return mb_strtolower(trim($accountId));
    }

    private function formatDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (string) Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeTimeForInput(string $value): string
    {
        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $value, $matches)) {
            return $matches[0];
        }

        return now()->format('H:i');
    }

    private function normalizeFileNameForMatching(string $fileName): string
    {
        $normalizedUnicode = $this->normalizeUnicode($fileName);
        $decomposed = $this->decomposeHangulSyllables($normalizedUnicode);
        $lower = mb_strtolower($decomposed);

        return preg_replace('/[^\p{L}\p{N}]/u', '', $lower) ?? $lower;
    }

    /**
     * @return array<int, string>
     */
    private function extractFileSearchTokens(string $fileName): array
    {
        $base = preg_replace('/\.[a-z0-9]{1,8}$/iu', '', $this->normalizeUnicode($fileName)) ?? $fileName;
        $parts = preg_split('/[\s_\-()\[\]{}.,]+/u', $base) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $candidate = trim((string) $part);
            if ($candidate === '') {
                continue;
            }

            if ($this->isOpaqueIdentifierToken($candidate)) {
                continue;
            }

            $hasKorean = preg_match('/[\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}]/u', $candidate) === 1;
            $minLength = $hasKorean ? 2 : 5;
            if (mb_strlen($candidate) < $minLength) {
                continue;
            }
            $tokens[] = $candidate;
        }

        return array_values(array_unique(array_slice($tokens, 0, 4)));
    }

    private function isOpaqueIdentifierToken(string $token): bool
    {
        if (preg_match('/^[a-z0-9]{15,30}$/i', $token) === 1) {
            return true;
        }

        return preg_match('/^[a-z0-9]{8,}-[a-z0-9-]{8,}$/i', $token) === 1;
    }

    /**
     * @param  array<int, string>  $targetTokens
     * @param  array<int, string>  $candidateTokens
     */
    private function calculateFilenameSimilarityScore(
        string $targetNormalized,
        array $targetTokens,
        string $candidateNormalized,
        array $candidateTokens
    ): float {
        $score = 0.0;

        if ($targetNormalized !== '' && ($candidateNormalized !== '')) {
            if (str_contains($candidateNormalized, $targetNormalized) || str_contains($targetNormalized, $candidateNormalized)) {
                $score += 0.25;
            }
        }

        if ($targetTokens !== [] && $candidateTokens !== []) {
            $targetSet = [];
            foreach ($targetTokens as $token) {
                $targetSet[$this->normalizeFileNameForMatching($token)] = true;
            }

            $candidateSet = [];
            foreach ($candidateTokens as $token) {
                $candidateSet[$this->normalizeFileNameForMatching($token)] = true;
            }

            $overlap = count(array_intersect_key($targetSet, $candidateSet));
            $targetCount = max(1, count($targetSet));
            $candidateCount = max(1, count($candidateSet));
            $jaccard = $overlap / max(1, count(array_unique(array_merge(array_keys($targetSet), array_keys($candidateSet)))));
            $coverage = $overlap / min($targetCount, $candidateCount);

            $score += ($jaccard * 0.5) + ($coverage * 0.5);
        }

        return min(1.0, $score);
    }

    private function normalizeUnicode(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            try {
                $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
                if (is_string($normalized) && $normalized !== '') {
                    return $normalized;
                }
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function decomposeHangulSyllables(string $value): string
    {
        $result = '';
        $length = mb_strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($value, $i, 1);
            $codePoint = mb_ord($char, 'UTF-8');

            if ($codePoint >= 0xAC00 && $codePoint <= 0xD7A3) {
                $syllableIndex = $codePoint - 0xAC00;
                $leadIndex = intdiv($syllableIndex, 588);
                $vowelIndex = intdiv($syllableIndex % 588, 28);
                $tailIndex = $syllableIndex % 28;

                $result .= mb_chr(0x1100 + $leadIndex, 'UTF-8');
                $result .= mb_chr(0x1161 + $vowelIndex, 'UTF-8');
                if ($tailIndex > 0) {
                    $result .= mb_chr(0x11A7 + $tailIndex, 'UTF-8');
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private function canQuerySfAccount(): bool
    {
        return Schema::hasTable('SF_Account')
            && Schema::hasColumn('SF_Account', 'account_ID')
            && Schema::hasColumn('SF_Account', 'Name')
            && Schema::hasColumn('SF_Account', 'GSKR_Contract__c')
            && Schema::hasColumn('SF_Account', 'GSKR_Gts_Type__c');
    }

    private function canQuerySfFiles(): bool
    {
        return Schema::hasTable('SF_Files')
            && Schema::hasColumn('SF_Files', 'ID')
            && Schema::hasColumn('SF_Files', 'fileName');
    }

    private function canQueryContractDocuments(): bool
    {
        return Schema::hasTable('contract_documents')
            && Schema::hasColumn('contract_documents', 'id')
            && Schema::hasColumn('contract_documents', 'original_filename');
    }

    private function emptyPaginator(string $pageName, int $perPage = 15): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: 1,
            options: ['path' => request()->url(), 'pageName' => $pageName]
        );
    }
}
