<?php

namespace App\Livewire;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\ContractDocument;
use App\Models\Institution;
use App\Models\SalesforceAccount;
use App\Models\SalesforceFile;
use App\Models\SupportRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SupportCreateForm extends Component
{
    use WithFileUploads;

    public string $formSkCode = '';

    public ?int $formPotentialTargetId = null;

    public string $formAccountName = '';

    public string $formInstitutionKeyword = '';

    public string $formCoName = '';

    public string $formSupportDate = '';

    public string $formSupportTime = '';

    public string $formSupportType = '전화';

    public string $formTarget = '';

    public string $formToAccount = '';

    /** 본사/타 부서 공유 (TO_Depart) */
    public string $formToDepart = '';

    public bool $formIsPotential = false;

    /** 잠재기관일 때만 사용하는 가능성 (A/B/C/D) */
    public string $formPossibility = '';

    public bool $formCompleted = false;

    /** @var TemporaryUploadedFile|null */
    public $sfUpload = null;

    protected array $rules = [
        'formSkCode' => ['nullable', 'required_without:formPotentialTargetId'],
        'formPotentialTargetId' => ['nullable', 'integer', 'required_without:formSkCode'],
        'formSupportDate' => 'required|date',
        'formSupportTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        'sfUpload' => [
            'nullable',
            'file',
            'max:20480',
            'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx',
        ],
    ];

    protected array $messages = [
        'formSkCode.required_without' => '기관을 선택해 주세요.',
        'formPotentialTargetId.required_without' => '기관을 선택해 주세요.',
        'formSupportDate.required' => '지원 날짜를 입력해 주세요.',
        'formSupportDate.date' => '올바른 날짜 형식이 아닙니다.',
        'formSupportTime.required' => '지원 시간을 입력해 주세요.',
        'formSupportTime.regex' => '지원 시간은 HH:MM 형식으로 입력해 주세요.',
        'sfUpload.max' => '파일 크기는 20MB 이하여야 합니다.',
        'sfUpload.mimes' => '허용 형식: PDF, 이미지, Word, Excel',
    ];

    public function mount(): void
    {
        $user = auth()->user();
        $this->formCoName = $user !== null ? $user->nameForCoReports() : '';
        $this->formSupportDate = now()->format('Y-m-d');
        $this->formSupportTime = now()->format('H:i');
    }

    public function updatedFormSkCode(string $value): void
    {
        if (blank($value)) {
            $this->formAccountName = '';
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';

            return;
        }

        $potential = $this->findPotentialBySkCode($value);
        $inst = Institution::query()->where('SKcode', $value)->first();
        $this->formAccountName = (string) ($inst?->AccountName ?? $potential?->AccountName ?? '');
        $this->formPotentialTargetId = $potential?->ID ? (int) $potential->ID : null;
        $this->formIsPotential = $potential !== null;
        $this->formPossibility = $potential ? (string) ($potential->Possibility ?? '') : '';
        if (filled($value)) {
            $this->applyDefaultCommunicationTemplatesIfEmpty();
        }
    }

    public function updatedFormInstitutionKeyword(string $value): void
    {
        $keyword = trim($value);

        if ($keyword === '') {
            $this->formSkCode = '';
            $this->formAccountName = '';
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';

            return;
        }

        $potential = $this->findPotentialByKeyword($keyword);
        if ($potential) {
            $this->formSkCode = trim((string) ($potential->AccountCode ?? ''));
            $this->formAccountName = (string) $potential->AccountName;
            $this->formPotentialTargetId = (int) $potential->ID;
            $this->formIsPotential = true;
            $this->formPossibility = (string) ($potential->Possibility ?? '');
            $this->applyDefaultCommunicationTemplatesIfEmpty();

            return;
        }

        $inst = Institution::query()
            ->where('AccountName', $keyword)
            ->orWhere('SKcode', $keyword)
            ->first();

        if ($inst) {
            $this->formSkCode = (string) $inst->SKcode;
            $this->formAccountName = (string) $inst->AccountName;
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';
            $this->applyDefaultCommunicationTemplatesIfEmpty();

            return;
        }

        $this->formSkCode = '';
        $this->formAccountName = '';
        $this->formPotentialTargetId = null;
        $this->formIsPotential = false;
        $this->formPossibility = '';
    }

    public function selectInstitution(string $skCode = '', bool $isPotential = false, ?int $potentialTargetId = null): void
    {
        $trimmedSkCode = trim($skCode);
        $inst = $trimmedSkCode !== ''
            ? Institution::query()->where('SKcode', $trimmedSkCode)->first()
            : null;
        $potential = $potentialTargetId !== null
            ? $this->findPotentialById($potentialTargetId)
            : null;
        if ($potential === null && $trimmedSkCode !== '') {
            $potential = $this->findPotentialBySkCode($trimmedSkCode);
        }

        if (! $inst && ! $isPotential && $potential === null) {
            return;
        }

        $this->formSkCode = $inst
            ? (string) $inst->SKcode
            : trim((string) ($potential?->AccountCode ?? $trimmedSkCode));
        $this->formAccountName = $inst
            ? (string) $inst->AccountName
            : (string) ($potential?->AccountName ?? '');
        $this->formInstitutionKeyword = $this->formAccountName;
        $this->formPotentialTargetId = ($isPotential || $potential !== null) && $potential?->ID
            ? (int) $potential->ID
            : null;
        $this->formIsPotential = $this->formPotentialTargetId !== null;
        $this->formPossibility = $this->formIsPotential ? (string) ($potential?->Possibility ?? '') : '';
        $this->applyDefaultCommunicationTemplatesIfEmpty();
    }

    /**
     * 기관 선택 직후, 소통 필드가 비어 있을 때만 config 템플릿을 넣습니다.
     */
    private function applyDefaultCommunicationTemplatesIfEmpty(): void
    {
        if (! $this->hasInstitutionSelection()) {
            return;
        }

        if ($this->formToAccount === '') {
            $this->formToAccount = (string) config('support_report_defaults.to_account_template', '');
        }

        if ($this->formToDepart === '') {
            $this->formToDepart = (string) config('support_report_defaults.to_depart_template', '');
        }
    }

    public function save(): void
    {
        $this->validate();

        $upload = $this->sfUpload;
        if ($upload instanceof TemporaryUploadedFile && blank($this->formSkCode)) {
            $this->addError('sfUpload', 'SK코드가 발급된 기관만 파일 업로드가 가능합니다. (미계약 잠재기관은 보고서만 저장)');

            return;
        }

        $resolvedPotentialTargetId = $this->resolveUncontractedPotentialTargetId();
        $storedPath = null;
        $originalFilename = null;
        $detectedMimeType = null;
        $detectedSize = null;

        try {
            if ($upload instanceof TemporaryUploadedFile) {
                // TemporaryUploadedFile은 storeAs 이후 임시 파일 메타 접근이 실패할 수 있어 사전 캡처합니다.
                $originalFilename = $upload->getClientOriginalName();
                $detectedMimeType = $upload->getMimeType();
                $detectedSize = $upload->getSize();

                $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $originalFilename) ?? 'support-file';
                $storedName = Str::uuid()->toString().'_'.$safeOriginal;
                $directory = 'contract-documents/'.$this->formSkCode;
                $storedPath = $upload->storeAs($directory, $storedName, 'local');

                if ($storedPath === false) {
                    $this->addError('sfUpload', '파일 저장에 실패했습니다.');

                    return;
                }
            }

            DB::transaction(function () use ($upload, $storedPath, $originalFilename, $detectedMimeType, $detectedSize, $resolvedPotentialTargetId): void {
                $supportRecord = SupportRecord::query()->create([
                    'Year' => (int) date('Y', strtotime($this->formSupportDate)),
                    'SK_Code' => $this->formSkCode !== '' ? $this->formSkCode : null,
                    'potential_target_id' => $resolvedPotentialTargetId,
                    'Account_Name' => $this->formAccountName,
                    'TR_Name' => $this->formCoName,
                    'Support_Date' => $this->formSupportDate,
                    'Meet_Time' => $this->formSupportTime.':00',
                    'Support_Type' => $this->formSupportType,
                    'Target' => $this->formTarget,
                    'Issue' => null,
                    'TO_Account' => $this->formToAccount,
                    'TO_Depart' => $this->formToDepart,
                    'Status' => $this->formCompleted ? '완료' : '진행중',
                    'CompletedDate' => $this->formCompleted ? now() : null,
                    'CreatedDate' => now(),
                ]);

                $this->mirrorSupportToPotentialDetail($supportRecord);

                if ($upload instanceof TemporaryUploadedFile && is_string($storedPath) && $storedPath !== '') {
                    $documentTime = strlen($this->formSupportTime) >= 5
                        ? substr($this->formSupportTime, 0, 5).':00'
                        : $this->formSupportTime;
                    $filenameForRecord = is_string($originalFilename) && $originalFilename !== ''
                        ? $originalFilename
                        : $upload->getClientOriginalName();

                    ContractDocument::query()->create([
                        'sk_code' => $this->formSkCode,
                        'account_name' => $this->formAccountName ?: '-',
                        'changed_account_name' => null,
                        'business_number' => null,
                        'document_date' => $this->formSupportDate,
                        'document_time' => $documentTime,
                        'consultant' => $this->formCoName ?: (string) (auth()->user()?->nameForCoReports() ?? ''),
                        'original_filename' => $filenameForRecord,
                        'stored_disk' => 'local',
                        'stored_path' => $storedPath,
                        'mime_type' => $detectedMimeType,
                        'size_bytes' => $detectedSize,
                        'uploaded_by' => auth()->user()?->nameForCoReports(),
                    ]);

                    if (Schema::hasTable('SF_Files')) {
                        SalesforceFile::query()->create([
                            'fileName' => $this->buildSfUploadFileName($filenameForRecord, $this->formAccountName),
                            'download_Cnt' => 0,
                            'LastUpdate_Date' => now()->format('Y-m-d H:i:s'),
                            'User' => (string) (auth()->user()?->nameForCoReports() ?? $this->formCoName),
                            'created_Date' => now()->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            if (is_string($storedPath) && $storedPath !== '' && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }

        $this->sfUpload = null;
        session()->flash('success', '지원 보고서가 저장되었습니다.');
        $this->redirectRoute('supports.index', navigate: true);
    }

    public function clearSfUpload(): void
    {
        $this->sfUpload = null;
        $this->resetValidation('sfUpload');
    }

    private function mirrorSupportToPotentialDetail(SupportRecord $supportRecord): void
    {
        $target = null;
        $potentialTargetId = (int) ($supportRecord->potential_target_id ?? 0);
        if ($potentialTargetId > 0) {
            $target = CoNewTarget::query()
                ->whereKey($potentialTargetId)
                ->where('IsContract', false)
                ->first();
        }

        if (! $target) {
            $skCode = trim((string) $supportRecord->SK_Code);
            if ($skCode === '') {
                return;
            }

            $target = CoNewTarget::query()
                ->where('AccountCode', $skCode)
                ->where('IsContract', false)
                ->orderByDesc('ID')
                ->first();
        }

        if (! $target) {
            return;
        }

        // 폼에서 입력한 가능성 값이 있으면 CoNewTarget에도 반영
        $possibility = filled($this->formPossibility) ? $this->formPossibility : ($target->Possibility ?: null);
        if (filled($this->formPossibility) && $this->formPossibility !== $target->Possibility) {
            $target->Possibility = $this->formPossibility;
            $target->save();
        }

        $descriptionBlocks = array_filter([
            filled($this->formToAccount) ? '[기관 소통내용]'.PHP_EOL.$this->formToAccount : null,
            filled($this->formToDepart) ? '[본사/타 부서 공유]'.PHP_EOL.$this->formToDepart : null,
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => (int) date('Y', strtotime($this->formSupportDate)),
            'AccountName' => (string) ($target->AccountName ?? $this->formAccountName),
            'AccountManager' => filled($target->AccountManager) ? $target->AccountManager : ($this->formCoName ?: null),
            'MeetingDate' => $this->formSupportDate,
            'MeetingTime' => $this->formSupportTime,
            'MeetingTime_End' => null,
            'Description' => implode(PHP_EOL.PHP_EOL, $descriptionBlocks),
            'ConsultingType' => $this->formSupportType,
            'Possibility' => $possibility,
        ]);
    }

    public function render(): View
    {
        $keyword = trim($this->formInstitutionKeyword);
        $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';

        // Eloquent\Collection::merge()는 항목을 모델로 간주해 getKey()를 호출한다.
        // 배열로 합치려면 일반 Support\Collection으로 바꾼 뒤 merge 한다.
        $institutionSuggestions = collect(
            Institution::query()
                ->where(function ($query) use ($normalizedKeyword): void {
                    if ($normalizedKeyword === '') {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                        ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                })
                ->orderBy('AccountName')
                ->limit(8)
                ->get(['SKcode', 'AccountName'])
        )->map(fn (Institution $inst): array => [
            'SKcode' => (string) $inst->SKcode,
            'AccountName' => (string) $inst->AccountName,
            'is_potential' => false,
            'potential_target_id' => null,
            'dedupe_key' => 'sk:'.(string) $inst->SKcode,
        ]);

        $potentialSuggestions = $this->potentialSuggestions($normalizedKeyword);

        $mergedSuggestions = $institutionSuggestions
            ->merge($potentialSuggestions)
            ->groupBy('dedupe_key')
            ->map(function (Collection $group): array {
                $potentialItem = $group->firstWhere('is_potential', true);
                $item = $potentialItem ?? $group->first();

                return [
                    'SKcode' => (string) ($item['SKcode'] ?? ''),
                    'AccountName' => (string) ($item['AccountName'] ?? ''),
                    'is_potential' => (bool) ($item['is_potential'] ?? false),
                    'potential_target_id' => isset($item['potential_target_id']) ? (int) $item['potential_target_id'] : null,
                    'dedupe_key' => (string) ($item['dedupe_key'] ?? ''),
                ];
            })
            ->sortBy('AccountName', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(8)
            ->values()
            ->map(fn (array $item): object => (object) $item);

        return view('livewire.support-create-form', [
            'institutionSuggestions' => $mergedSuggestions,
        ]);
    }

    private function findPotentialBySkCode(string $skCode): ?CoNewTarget
    {
        $trimmedSk = trim($skCode);
        if ($trimmedSk === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where('AccountCode', $trimmedSk)
            ->orderByDesc('ID')
            ->first();
    }

    private function findPotentialById(?int $potentialTargetId): ?CoNewTarget
    {
        if ($potentialTargetId === null || $potentialTargetId <= 0) {
            return null;
        }

        return CoNewTarget::query()
            ->whereKey($potentialTargetId)
            ->where('IsContract', false)
            ->first();
    }

    private function findPotentialByKeyword(string $keyword): ?CoNewTarget
    {
        $trimmedKeyword = trim($keyword);
        if ($trimmedKeyword === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where(function ($query) use ($trimmedKeyword): void {
                $query->where('AccountName', $trimmedKeyword)
                    ->orWhere('AccountCode', $trimmedKeyword);
            })
            ->orderByDesc('ID')
            ->first();
    }

    private function potentialSuggestions(string $normalizedKeyword): Collection
    {
        if ($normalizedKeyword === '') {
            return collect();
        }

        return collect(
            CoNewTarget::query()
                ->where('IsContract', false)
                ->where(function ($query) use ($normalizedKeyword): void {
                    $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                        ->orWhereRaw("REPLACE(IFNULL(AccountCode,''), ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                })
                ->orderBy('AccountName')
                ->limit(8)
                ->get(['ID', 'AccountCode', 'AccountName'])
        )->map(fn (CoNewTarget $target): array => [
            'SKcode' => trim((string) ($target->AccountCode ?? '')),
            'AccountName' => (string) $target->AccountName,
            'is_potential' => true,
            'potential_target_id' => (int) $target->ID,
            'dedupe_key' => filled($target->AccountCode)
                ? 'sk:'.(string) $target->AccountCode
                : 'pot:'.(int) $target->ID,
        ]);
    }

    private function hasInstitutionSelection(): bool
    {
        return filled($this->formSkCode) || $this->formPotentialTargetId !== null;
    }

    private function resolveUncontractedPotentialTargetId(): ?int
    {
        if ($this->formPotentialTargetId === null || $this->formPotentialTargetId <= 0) {
            return null;
        }

        $target = CoNewTarget::query()
            ->whereKey($this->formPotentialTargetId)
            ->where('IsContract', false)
            ->first(['ID']);

        return $target?->ID ? (int) $target->ID : null;
    }

    private function buildSfUploadFileName(string $originalFilename, string $accountName): string
    {
        $fallback = trim($originalFilename) !== '' ? $originalFilename : 'uploaded-file';
        $accountId = $this->resolveSalesforceAccountIdByName($accountName);

        if ($accountId === '') {
            return $fallback;
        }

        if (str_starts_with($fallback, $accountId.'_')) {
            return $fallback;
        }

        return $accountId.'_'.$fallback;
    }

    private function resolveSalesforceAccountIdByName(string $accountName): string
    {
        if (! Schema::hasTable('SF_Account')
            || ! Schema::hasColumn('SF_Account', 'account_ID')
            || ! Schema::hasColumn('SF_Account', 'Name')) {
            return '';
        }

        $trimmedName = trim($accountName);
        if ($trimmedName === '') {
            return '';
        }

        $exact = SalesforceAccount::query()
            ->where('Name', $trimmedName)
            ->whereNotNull('account_ID')
            ->orderByDesc('ID')
            ->value('account_ID');

        if (filled($exact)) {
            return trim((string) $exact);
        }

        $normalizedTarget = $this->normalizeNameForMatch($trimmedName);
        if ($normalizedTarget === '') {
            return '';
        }

        $candidates = SalesforceAccount::query()
            ->select(['account_ID', 'Name'])
            ->whereNotNull('account_ID')
            ->orderByDesc('ID')
            ->limit(1000)
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->normalizeNameForMatch((string) ($candidate->Name ?? '')) === $normalizedTarget) {
                return trim((string) ($candidate->account_ID ?? ''));
            }
        }

        return '';
    }

    private function normalizeNameForMatch(string $value): string
    {
        $normalized = $value;
        if (class_exists(\Normalizer::class)) {
            $normalizedValue = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalizedValue) && $normalizedValue !== '') {
                $normalized = $normalizedValue;
            }
        }

        $lower = mb_strtolower($normalized);

        return preg_replace('/[^\p{L}\p{N}]/u', '', $lower) ?? $lower;
    }
}
