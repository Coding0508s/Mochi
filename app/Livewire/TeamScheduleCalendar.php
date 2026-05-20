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

    public string $displayMode = 'calendar';

    public string $filterType = '';

    public string $filterStatus = '';

    public bool $showFormModal = false;

    public bool $showDayModal = false;

    public string $selectedDay = '';

    public ?int $editingScheduleId = null;

    public bool $viewOnly = false;

    public bool $showRecurrenceDeleteModal = false;

    public string $recurrenceDeleteScope = 'single';

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

    public string $recurrenceRule = '';

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

    public function updatedDisplayMode(): void
    {
        if (! in_array($this->displayMode, ['calendar', 'list'], true)) {
            $this->displayMode = 'calendar';
        }
    }

    public function updatedFilterType(): void
    {
        if (! in_array($this->filterType, ['', 'meeting', 'task', 'personal', 'etc'], true)) {
            $this->filterType = '';
        }
    }

    public function updatedFilterStatus(): void
    {
        if (! in_array($this->filterStatus, ['', 'planned', 'done', 'cancelled'], true)) {
            $this->filterStatus = '';
        }
    }

    public function openCreateModal(string $date): void
    {
        Gate::authorize('create', TeamSchedule::class);

        $this->resetForm();
        $this->closeDayModal();
        $this->date = Carbon::parse($date)->format('Y-m-d');
        $this->startTime = '09:00';
        $this->endTime = '10:00';
        $this->showFormModal = true;
    }

    public function openDayModal(string $date): void
    {
        $this->selectedDay = Carbon::parse($date)->format('Y-m-d');
        $this->showDayModal = true;
    }

    public function closeDayModal(): void
    {
        $this->showDayModal = false;
        $this->selectedDay = '';
    }

    public function openEditModal(int $id): void
    {
        $schedule = TeamSchedule::query()->with('user')->findOrFail($id);
        Gate::authorize('view', $schedule);

        $this->closeDayModal();
        $this->editingScheduleId = $schedule->id;
        $this->viewOnly = Gate::denies('update', $schedule);
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
        $this->recurrenceRule = (string) ($schedule->recurrence_rule ?? '');
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->showRecurrenceDeleteModal = false;
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
            $payload = [
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
            ];

            if ($schedule->recurrence_parent_id === null) {
                $payload['recurrence_rule'] = $validated['recurrenceRule'] ?: null;
            }

            $schedule->update($payload);
        } else {
            Gate::authorize('create', TeamSchedule::class);
            $schedule = TeamSchedule::query()->create([
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
                'recurrence_rule' => $validated['recurrenceRule'] ?: null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $this->createRecurrenceChildren($schedule);
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

        if ($this->isRecurringSchedule($schedule)) {
            $this->showRecurrenceDeleteModal = true;

            return;
        }

        $this->deleteSchedule($schedule);
    }

    public function confirmRecurringDelete(): void
    {
        if ($this->editingScheduleId === null) {
            return;
        }

        $schedule = TeamSchedule::query()->findOrFail($this->editingScheduleId);
        Gate::authorize('delete', $schedule);

        if ($this->recurrenceDeleteScope === 'all_following') {
            $seriesId = (int) ($schedule->recurrence_parent_id ?: $schedule->id);
            TeamSchedule::query()
                ->where(function (Builder $query) use ($seriesId): void {
                    $query->whereKey($seriesId)
                        ->orWhere('recurrence_parent_id', $seriesId);
                })
                ->where('starts_at', '>=', $schedule->starts_at)
                ->delete();

            session()->flash('success', '반복 일정이 삭제되었습니다.');
            $this->closeFormModal();

            return;
        }

        $this->deleteSchedule($schedule);
    }

    public function cancelRecurringDelete(): void
    {
        $this->showRecurrenceDeleteModal = false;
        $this->recurrenceDeleteScope = 'single';
    }

    private function deleteSchedule(TeamSchedule $schedule): void
    {
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
            'recurrenceRule' => ['nullable', Rule::in(['', 'weekly', 'biweekly', 'monthly'])],
        ];
    }

    public function render(): View
    {
        $calendar = $this->calendarDays();
        $schedules = $this->visibleSchedules()
            ->get()
            ->groupBy(fn (TeamSchedule $schedule): string => $schedule->starts_at->format('Y-m-d'));
        $listSchedules = $this->visibleSchedules()->get();
        $daySchedules = $this->selectedDay !== ''
            ? $this->visibleSchedules()
                ->whereDate('starts_at', Carbon::parse($this->selectedDay)->toDateString())
                ->get()
            : collect();

        return view('livewire.team-schedule-calendar', [
            'calendar' => $calendar,
            'schedulesByDate' => $schedules,
            'listSchedules' => $listSchedules,
            'daySchedules' => $daySchedules,
            'monthLabel' => Carbon::parse($this->month.'-01')->format('Y년 n월'),
            'teamUsers' => $this->teamUsers(),
        ]);
    }

    private function visibleSchedules(): Builder
    {
        $user = auth()->user();
        $userWorkdept = $this->currentUserWorkdept();

        return TeamSchedule::query()
            ->with('user')
            ->forMonth($this->month)
            ->when($this->viewMode === 'mine', function (Builder $query) use ($user): void {
                $query->where('user_id', $user?->id);
            })
            ->when($this->viewMode === 'team', function (Builder $query) use ($user, $userWorkdept): void {
                if ($userWorkdept === null) {
                    $query->where('user_id', $user?->id);

                    return;
                }

                $query->where(function (Builder $visibleQuery) use ($user, $userWorkdept): void {
                    $visibleQuery->where('user_id', $user?->id)
                        ->orWhere(function (Builder $teamQuery) use ($userWorkdept): void {
                            $teamQuery->where('visibility', 'team')
                                ->whereHas('user', function (Builder $ownerQuery) use ($userWorkdept): void {
                                    $ownerQuery
                                        ->whereNotNull('employee_empno')
                                        ->whereHas('employee', function (Builder $empQuery) use ($userWorkdept): void {
                                            $empQuery->where('WORKDEPT', $userWorkdept);
                                        });
                                });
                        });
                });
            })
            ->when($this->viewMode === 'team' && $this->userFilter !== null && $user?->hasFullAccess(), function (Builder $query): void {
                $query->where('user_id', $this->userFilter);
            })
            ->when($this->filterType !== '', function (Builder $query): void {
                $query->where('type', $this->filterType);
            })
            ->when($this->filterStatus !== '', function (Builder $query): void {
                $query->where('status', $this->filterStatus);
            })
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    private function createRecurrenceChildren(TeamSchedule $schedule): void
    {
        $rule = (string) ($schedule->recurrence_rule ?? '');
        if ($rule === '') {
            return;
        }

        $count = match ($rule) {
            'weekly' => 52,
            'biweekly' => 26,
            'monthly' => 12,
            default => 0,
        };

        if ($count === 0) {
            return;
        }

        for ($i = 1; $i <= $count; $i++) {
            $startsAt = $this->nextRecurringDate($schedule->starts_at, $rule, $i);
            $endsAt = $schedule->ends_at
                ? $this->nextRecurringDate($schedule->ends_at, $rule, $i)
                : null;

            TeamSchedule::query()->create([
                'user_id' => $schedule->user_id,
                'title' => $schedule->title,
                'description' => $schedule->description,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_all_day' => $schedule->is_all_day,
                'type' => $schedule->type,
                'visibility' => $schedule->visibility,
                'status' => $schedule->status,
                'location' => $schedule->location,
                'recurrence_rule' => $rule,
                'recurrence_parent_id' => $schedule->id,
                'created_by' => $schedule->created_by,
                'updated_by' => $schedule->updated_by,
            ]);
        }
    }

    private function nextRecurringDate(Carbon $date, string $rule, int $index): Carbon
    {
        return match ($rule) {
            'weekly' => $date->copy()->addWeeks($index),
            'biweekly' => $date->copy()->addWeeks($index * 2),
            'monthly' => $date->copy()->addMonthsNoOverflow($index),
            default => $date->copy(),
        };
    }

    private function isRecurringSchedule(TeamSchedule $schedule): bool
    {
        return filled($schedule->recurrence_rule) || $schedule->recurrence_parent_id !== null;
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
        $userWorkdept = $this->currentUserWorkdept();

        if ($userWorkdept === null) {
            return User::query()->whereRaw('0 = 1')->get(['id', 'name', 'email', 'team']);
        }

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('employee_empno')
            ->whereHas('employee', function (Builder $empQuery) use ($userWorkdept): void {
                $empQuery->where('WORKDEPT', $userWorkdept);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team']);
    }

    private function currentUserWorkdept(): ?string
    {
        $workdept = auth()->user()?->employee?->WORKDEPT;

        return filled($workdept) ? (string) $workdept : null;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingScheduleId = null;
        $this->viewOnly = false;
        $this->showRecurrenceDeleteModal = false;
        $this->recurrenceDeleteScope = 'single';
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
        $this->recurrenceRule = '';
    }
}
