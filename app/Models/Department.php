<?php

namespace App\Models;

use App\Support\DepartmentDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Department extends Model
{
    protected $table = 'department';

    protected $primaryKey = 'DEPTNO';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'DEPTNO',
        'DEPTNAME',
        'MGRNO',
        'ADMRDEPT',
        'LOCATION',
        'FGC_CreateDate',
        'FGC_LastModifier',
        'FGC_LastModifyDate',
        'FGC_Creator',
    ];

    /**
     * People 사이드바 팀 메뉴 상단 고정 순서 (DEPTNAME 기준, 대소문자 무시).
     *
     * @var list<string>
     */
    public const PEOPLE_SIDEBAR_PRIORITY_DEPT_NAMES = [
        'CEO',
        'Board of Directors',
    ];

    public function displayName(): string
    {
        return DepartmentDisplay::name((string) $this->DEPTNO, $this->DEPTNAME);
    }

    /**
     * People 사이드바에서 Online Team 부서 메뉴 슬롯 → 비활성 직원 목록으로 대체.
     */
    public function showsInactiveEmployeesInSidebar(): bool
    {
        return strcasecmp(trim((string) $this->DEPTNAME), 'Online Team') === 0;
    }

    public function peopleSidebarSortRank(): int
    {
        $name = trim((string) $this->DEPTNAME);

        foreach (self::PEOPLE_SIDEBAR_PRIORITY_DEPT_NAMES as $index => $priorityName) {
            if (strcasecmp($name, $priorityName) === 0) {
                return $index;
            }
        }

        return count(self::PEOPLE_SIDEBAR_PRIORITY_DEPT_NAMES);
    }

    /**
     * @param  Collection<int, self>  $departments
     * @return Collection<int, self>
     */
    public static function sortForPeopleSidebar(Collection $departments): Collection
    {
        return $departments
            ->sort(function (self $left, self $right): int {
                $rankCompare = $left->peopleSidebarSortRank() <=> $right->peopleSidebarSortRank();
                if ($rankCompare !== 0) {
                    return $rankCompare;
                }

                return strcmp((string) $left->DEPTNO, (string) $right->DEPTNO);
            })
            ->values();
    }
}
