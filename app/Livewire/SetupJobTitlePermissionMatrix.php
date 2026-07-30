<?php

namespace App\Livewire;

use App\Models\JobTitlePermission;
use App\Models\SetupCommonCode;
use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SetupJobTitlePermissionMatrix extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, string> */
    public array $flagLabels = [
        'setup_view' => 'Setup 조회',
        'setup_manage' => 'Setup 관리',
        'can_manage_store_inventory' => 'Store 재고 수정',
        'is_gs_brochure_admin' => 'GS Brochure 관리',
        'is_coach_team_lead' => 'Coach 팀 KPI',
        'can_view_all_institutions' => '기관 전체 조회',
        'is_deputy_admin' => '부관리자',
    ];

    public function mount(): void
    {
        $this->loadRows();
    }

    public function save(): void
    {
        Gate::authorize('manageTeamStructure');

        $allowedJobCodes = $this->activeJobTitleCodes();
        $rowsToSave = array_intersect_key($this->rows, array_flip($allowedJobCodes));

        $rules = [];
        foreach (array_keys($rowsToSave) as $jobCode) {
            foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
                $rules["rows.{$jobCode}.{$column}"] = ['boolean'];
            }
        }

        $this->validate($rules);

        $synchronizer = app(JobTitlePermissionSynchronizer::class);
        $actor = auth()->user();
        $totalSynced = 0;

        foreach ($rowsToSave as $jobCode => $row) {
            $flags = [];
            foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
                $flags[$column] = (bool) ($row[$column] ?? false);
            }

            if ($flags['setup_manage']) {
                $flags['setup_view'] = true;
            }

            JobTitlePermission::query()->updateOrCreate(
                ['job_code' => $jobCode],
                $flags
            );

            $totalSynced += $synchronizer->syncUsersForJobCode($jobCode, $actor);
        }

        $this->loadRows();

        session()->flash(
            'success',
            "직책 권한이 저장되었습니다. 연동 계정 {$totalSynced}건이 동기화되었습니다."
        );
    }

    public function render()
    {
        return view('livewire.setup-job-title-permission-matrix', [
            'canManage' => Gate::allows('manageTeamStructure'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function activeJobTitleCodes(): array
    {
        return SetupCommonCode::query()
            ->where('category', 'job_title')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    private function loadRows(): void
    {
        $codes = SetupCommonCode::query()
            ->where('category', 'job_title')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'label']);

        $existing = JobTitlePermission::query()
            ->whereIn('job_code', $codes->pluck('code'))
            ->get()
            ->keyBy('job_code');

        $this->rows = [];

        foreach ($codes as $code) {
            $row = $existing->get($code->code);
            $entry = [
                'label' => $code->label !== '' ? $code->label : $code->code,
            ];

            foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
                $entry[$column] = (bool) ($row?->{$column} ?? false);
            }

            $this->rows[$code->code] = $entry;
        }
    }
}
