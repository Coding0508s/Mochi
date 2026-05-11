<?php

namespace App\Livewire;

use App\Models\TeamSchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TeamScheduleCalendar extends Component
{
    public string $month = '';

    public string $viewMode = 'mine';

    public ?int $userFilter = null;

    public bool $showFormModal = false;

    public ?int $editingScheduleId = null;

    public string $title = '';

    public string $description = '';

    public string $date = '';

    public string $startTime = '';

    public string $endTime = '';

    public bool $isAllDay = false;

    public string $type = 'etc';

    public string $visibility = 'private';

    public string $status = 'planned';

    public string $location = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedViewMode(): void
    {
        if ($this->viewMode !== 'team') {
            $this->userFilter = null;
        }
    }

    public function openCreateModal(string $date): void
    {
        Gate::authorize('create', TeamSchedule::class);

        $this->resetForm();
        $this->date = Carbon::parse($date)->format('Y-m-d');
        $this->startTime = '09:00';
        $this->endTime = '10:00';
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $schedule = TeamSchedule::query()->with('user')->findOrFail($id);
        Gate::authorize('view', $schedule);

        $this->editingScheduleId = $schedule->id;
        $this->title = (string) $schedule->title;
        $this->description = (string) ($schedule->description ?? '');
        $this->date = $schedule->starts_at->format('Y-m-d');
        $this->startTime = $schedule->starts_at->format('H:i');
        $this->endTime = $schedule->ends_at?->format('H:i') ?? '';
        $this->isAllDay = (bool) $schedule->is_all_day;
        $this->type = (string) $schedule->type;
        $this->visibility = (string) $schedule->visibility;
        $this->status = (string) $schedule->status;
        $this->location = (string) ($schedule->location ?? '');
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate();
        $user = auth()->user();

        $startsAt = Carbon::parse($validated['date'].' '.($validated['isAllDay'] ? '00:00' : $validated['startTime']));
        $endsAt = $validated['isAllDay']
            ? $startsAt->copy()->endOfDay()
            : (filled($validated['endTime']) ? Carbon::parse($validated['date'].' '.$validated['endTime']) : null);

        if ($endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            $this->addError('endTime', '종료 시간은 시작 시간보다 늦어야 합니다.');

            return;
        }

        if ($this->editingScheduleId !== null) {
            $schedule = TeamSchedule::query()->findOrFail($this->editingScheduleId);
            Gate::authorize('update', $schedule);
            $schedule->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_all_day' => (bool) $validated['isAllDay'],
                'type' => $validated['type'],
                'visibility' => $validated['visibility'],
                'status' => $validated['status'],
                'location' => $validated['location'] ?: null,
                'updated_by' => $user?->id,
            ]);
        } else {
            Gate::authorize('create', TeamSchedule::class);
            TeamSchedule::query()->create([
                'user_id' => $user?->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_all_day' => (bool) $validated['isAllDay'],
                'type' => $validated['type'],
                'visibility' => $validated['visibility'],
                'status' => $validated['status'],
                'location' => $validated['location'] ?: null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
        }

        session()->flash('success', '일정이 저장되었습니다.');
        $this->closeFormModal();
    }

    public function delete(): void
    {
        if ($this->editingScheduleId === null) {
            return;
        }

        $schedule = TeamSchedule::query()->findOrFail($this->editingScheduleId);
        Gate::authorize('delete', $schedule);
        $schedule->delete();

        session()->flash('success', '일정이 삭제되었습니다.');
        $this->closeFormModal();
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'startTime' => [Rule::requiredIf(fn (): bool => ! $this->isAllDay), 'nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'isAllDay' => ['boolean'],
            'type' => ['required', Rule::in(['meeting', 'task', 'personal', 'etc'])],
            'visibility' => ['required', Rule::in(['private', 'team'])],
            'status' => ['required', Rule::in(['planned', 'done', 'cancelled'])],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function render(): View
    {
        $calendar = $this->calendarDays();
        $schedules = $this->visibleSchedules()
            ->get()
            ->groupBy(fn (TeamSchedule $schedule): string => $schedule->starts_at->format('Y-m-d'));

        return view('livewire.team-schedule-calendar', [
            'calendar' => $calendar,
            'schedulesByDate' => $schedules,
            'monthLabel' => Carbon::parse($this->month.'-01')->format('Y년 n월'),
            'teamUsers' => $this->teamUsers(),
        ]);
    }

    private function visibleSchedules(): Builder
    {
        $user = auth()->user();

        return TeamSchedule::query()
            ->with('user')
            ->forMonth($this->month)
            ->when($this->viewMode === 'mine', function (Builder $query) use ($user): void {
                $query->where('user_id', $user?->id);
            })
            ->when($this->viewMode === 'team', function (Builder $query) use ($user): void {
                $query->where(function (Builder $visibleQuery) use ($user): void {
                    $visibleQuery->where('user_id', $user?->id)
                        ->orWhere(function (Builder $teamQuery) use ($user): void {
                            $teamQuery->where('visibility', 'team')
                                ->whereHas('user', function (Builder $ownerQuery) use ($user): void {
                                    $ownerQuery
                                        ->where('team', $user?->team)
                                        ->whereNotNull('employee_empno')
                                        ->whereHas('employee');
                                });
                        });

                    if ($user?->hasFullAccess()) {
                        $visibleQuery->orWhereRaw('1 = 1');
                    }
                });
            })
            ->when($this->viewMode === 'team' && $this->userFilter !== null && $user?->hasFullAccess(), function (Builder $query): void {
                $query->where('user_id', $this->userFilter);
            })
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * @return array<int, array{date: string, day: int, currentMonth: bool, today: bool}>
     */
    private function calendarDays(): array
    {
        $monthStart = Carbon::parse($this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        return collect(CarbonPeriod::create($gridStart, $gridEnd))
            ->map(fn (Carbon $date): array => [
                'date' => $date->format('Y-m-d'),
                'day' => (int) $date->format('j'),
                'currentMonth' => $date->isSameMonth($monthStart),
                'today' => $date->isToday(),
            ])
            ->all();
    }

    private function teamUsers()
    {
        $user = auth()->user();

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('employee_empno')
            ->whereHas('employee')
            ->when(! $user?->hasFullAccess(), function (Builder $query) use ($user): void {
                $query->where('team', $user?->team);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team']);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingScheduleId = null;
        $this->title = '';
        $this->description = '';
        $this->date = '';
        $this->startTime = '';
        $this->endTime = '';
        $this->isAllDay = false;
        $this->type = 'etc';
        $this->visibility = 'private';
        $this->status = 'planned';
        $this->location = '';
    }
}
