<?php

namespace App\Livewire;

use App\Models\ContractDocument;
use App\Models\SalesforceFile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class SalesforceFileList extends Component
{
    use WithPagination;

    public string $filterUser = '';

    public string $filterSkCode = '';

    public string $search = '';

    public ?int $deleteTargetId = null;

    public string $deleteTargetFileName = '';

    public bool $showDeleteModal = false;

    public function updatingFilterUser(): void
    {
        $this->resetPage(pageName: 'sfPage');
    }

    public function updatingFilterSkCode(): void
    {
        $this->resetPage(pageName: 'contractPage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'sfPage');
        $this->resetPage(pageName: 'contractPage');
    }

    public function confirmDeleteContractDocument(int $id): void
    {
        $document = ContractDocument::query()->findOrFail($id);
        $this->deleteTargetId = $document->id;
        $this->deleteTargetFileName = $document->original_filename;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
        $this->deleteTargetFileName = '';
    }

    public function deleteContractDocument(): void
    {
        if ($this->deleteTargetId === null) {
            return;
        }

        $document = ContractDocument::query()->findOrFail($this->deleteTargetId);
        $disk = $document->stored_disk ?: 'local';

        if (Storage::disk($disk)->exists($document->stored_path)) {
            Storage::disk($disk)->delete($document->stored_path);
        }

        $document->delete();
        $this->closeDeleteModal();
        session()->flash('success', '계약서 파일을 삭제했습니다.');
    }

    public function render(): View
    {
        $users = SalesforceFile::query()
            ->whereNotNull('User')
            ->where('User', '!=', '')
            ->distinct()
            ->orderBy('User')
            ->pluck('User');

        $sfFiles = SalesforceFile::query()
            ->when(filled($this->filterUser), function ($query): void {
                $query->where('User', $this->filterUser);
            })
            ->when(filled($this->search), function ($query): void {
                $keyword = trim($this->search);
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('fileName', 'like', "%{$keyword}%")
                        ->orWhere('User', 'like', "%{$keyword}%")
                        ->orWhere('created_Date', 'like', "%{$keyword}%")
                        ->orWhere('LastUpdate_Date', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('ID')
            ->paginate(15, pageName: 'sfPage');

        $contractDocuments = ContractDocument::query()
            ->when(filled($this->filterSkCode), function ($query): void {
                $query->where('sk_code', $this->filterSkCode);
            })
            ->when(filled($this->search), function ($query): void {
                $keyword = trim($this->search);
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('original_filename', 'like', "%{$keyword}%")
                        ->orWhere('account_name', 'like', "%{$keyword}%")
                        ->orWhere('business_number', 'like', "%{$keyword}%")
                        ->orWhere('sk_code', 'like', "%{$keyword}%")
                        ->orWhere('consultant', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15, pageName: 'contractPage');

        $skCodes = ContractDocument::query()
            ->whereNotNull('sk_code')
            ->distinct()
            ->orderBy('sk_code')
            ->pluck('sk_code');

        $contractCandidates = ContractDocument::query()
            ->when(filled($this->filterSkCode), function ($query): void {
                $query->where('sk_code', $this->filterSkCode);
            })
            ->when(filled($this->search), function ($query): void {
                $keyword = trim($this->search);
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('original_filename', 'like', "%{$keyword}%")
                        ->orWhere('account_name', 'like', "%{$keyword}%")
                        ->orWhere('business_number', 'like', "%{$keyword}%")
                        ->orWhere('sk_code', 'like', "%{$keyword}%")
                        ->orWhere('consultant', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id')
            ->get(['id', 'original_filename']);

        [$sfLinkedDocIds, $sfMatchTypes] = $this->buildSfFileLinks(
            $sfFiles->getCollection()->pluck('fileName')->all(),
            $contractCandidates
        );

        $docIdsToCheck = $contractDocuments->getCollection()
            ->pluck('id')
            ->merge(array_values($sfLinkedDocIds))
            ->filter(fn ($id): bool => filled($id))
            ->unique()
            ->values();

        $fileAvailableByDocId = ContractDocument::query()
            ->whereIn('id', $docIdsToCheck)
            ->get(['id', 'stored_disk', 'stored_path'])
            ->mapWithKeys(function (ContractDocument $doc): array {
                $disk = $doc->stored_disk ?: 'local';
                $exists = filled($doc->stored_path) && Storage::disk($disk)->exists($doc->stored_path);

                return [(int) $doc->id => $exists];
            })
            ->all();

        return view('livewire.salesforce-file-list', [
            'sfFiles' => $sfFiles,
            'contractDocuments' => $contractDocuments,
            'users' => $users,
            'skCodes' => $skCodes,
            'sfLinkedDocIds' => $sfLinkedDocIds,
            'sfMatchTypes' => $sfMatchTypes,
            'fileAvailableByDocId' => $fileAvailableByDocId,
        ]);
    }

    /**
     * @param array<int, mixed> $sfFileNames
     * @return array{0: array<string, int>, 1: array<string, string>}
     */
    private function buildSfFileLinks(array $sfFileNames, $contractCandidates): array
    {
        $exactMap = [];
        $normalizedMap = [];

        foreach ($contractCandidates as $doc) {
            $original = (string) ($doc->original_filename ?? '');
            if ($original === '') {
                continue;
            }

            $exactKey = mb_strtolower($original);
            $normalizedKey = $this->normalizeFileName($original);

            // 최신 id(desc) 우선이므로 이미 있으면 유지
            if (! isset($exactMap[$exactKey])) {
                $exactMap[$exactKey] = (int) $doc->id;
            }
            if (! isset($normalizedMap[$normalizedKey])) {
                $normalizedMap[$normalizedKey] = (int) $doc->id;
            }
        }

        $linked = [];
        $matchTypes = [];
        $normalizedKeys = array_keys($normalizedMap);

        foreach ($sfFileNames as $rawName) {
            $sfName = (string) $rawName;
            if ($sfName === '') {
                continue;
            }

            $exactKey = mb_strtolower($sfName);
            if (isset($exactMap[$exactKey])) {
                $linked[$sfName] = $exactMap[$exactKey];
                $matchTypes[$sfName] = 'exact';
                continue;
            }

            $normalized = $this->normalizeFileName($sfName);
            if (isset($normalizedMap[$normalized])) {
                $linked[$sfName] = $normalizedMap[$normalized];
                $matchTypes[$sfName] = 'normalized';
                continue;
            }

            // 유사 매칭: normalized 부분 포함(너무 짧은 이름 제외)
            if (mb_strlen($normalized) < 6) {
                continue;
            }

            foreach ($normalizedKeys as $candidateKey) {
                if (str_contains($candidateKey, $normalized) || str_contains($normalized, $candidateKey)) {
                    $linked[$sfName] = $normalizedMap[$candidateKey];
                    $matchTypes[$sfName] = 'fuzzy';
                    break;
                }
            }
        }

        return [$linked, $matchTypes];
    }

    private function normalizeFileName(string $fileName): string
    {
        $lower = mb_strtolower($fileName);

        return preg_replace('/[^a-z0-9가-힣]/u', '', $lower) ?? $lower;
    }
}
