<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Mochi3(운영) → 현재 연결 DB(Mochi4) 레거시 테이블 변경분 동기화.
 * Mochi3에는 쓰지 않으며 users·migrations·g5_* 등은 대상에서 제외합니다.
 */
final class Mochi3ToMochi4Sync
{
    public const SOURCE_DATABASE = 'Mochi3';

    /** @var list<string> */
    private const SYNCABLE_TABLES = [
        'S_AccountName',
        'S_Account_Information',
        'S_GSNumber',
        'Teachers',
        'S_SupportInfo_Account',
        'S_Support_NewTeacher',
        'S_Support_LVA',
        'S_Support_OnSite',
        'S_Support_OpenClass',
        'S_SupportLittleSEED_ONLVA',
        'S_Support_U21',
        'S_Support_U31',
        'S_SolutionConsulting',
        'S_RetirementList',
        'S_TeacherMasterDB',
        'employee',
        'department',
        'S_CO_NewTarget',
        'S_CO_NewTarget_Detail',
    ];

    /**
     * @var array<string, array{join_on: list<string>, protect_empty: list<string>, insert_missing: bool}>
     */
    private const TABLE_OPTIONS = [
        'S_AccountName' => [
            'join_on' => ['ID', 'SKcode'],
            'protect_empty' => ['PortalCampusID'],
            'insert_missing' => false,
        ],
        'S_Account_Information' => [
            'join_on' => ['ID', 'SK_Code'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_GSNumber' => [
            'join_on' => ['ID', 'SKCode'],
            'protect_empty' => [],
            'insert_missing' => false,
        ],
        'Teachers' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_SupportInfo_Account' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_NewTeacher' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_LVA' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_OnSite' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_OpenClass' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_SupportLittleSEED_ONLVA' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_U21' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_Support_U31' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_SolutionConsulting' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_RetirementList' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_TeacherMasterDB' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'employee' => [
            'join_on' => ['EMPNO'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'department' => [
            'join_on' => ['DEPTNO'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_CO_NewTarget' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
        'S_CO_NewTarget_Detail' => [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => true,
        ],
    ];

    /**
     * @return list<array{table: string, would_update: int, updated: int, would_insert: int, inserted: int, skipped_reason: string|null}>
     */
    public function run(bool $dryRun = false): array
    {
        $targetDatabase = $this->targetDatabaseName();
        $this->assertDatabasesExist($targetDatabase);

        $results = [];

        foreach (self::SYNCABLE_TABLES as $table) {
            $results[] = $this->syncTable($table, $targetDatabase, $dryRun);
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function syncableTables(): array
    {
        return self::SYNCABLE_TABLES;
    }

    public function targetDatabaseName(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    /**
     * @return array{table: string, would_update: int, updated: int, would_insert: int, inserted: int, skipped_reason: string|null}
     */
    private function syncTable(string $table, string $targetDatabase, bool $dryRun): array
    {
        $source = self::SOURCE_DATABASE;
        $options = self::TABLE_OPTIONS[$table] ?? [
            'join_on' => ['ID'],
            'protect_empty' => [],
            'insert_missing' => false,
        ];

        $empty = static fn (?string $reason = null): array => [
            'table' => $table,
            'would_update' => 0,
            'updated' => 0,
            'would_insert' => 0,
            'inserted' => 0,
            'skipped_reason' => $reason,
        ];

        if (! Schema::hasTable($table)) {
            return $empty('대상 테이블 없음');
        }

        if (! $this->tableExistsInDatabase($source, $table)) {
            return $empty('Mochi3에 테이블 없음');
        }

        if (! $this->hasModifyDateColumn($source, $table)) {
            return $empty('FGC_LastModifyDate 없음');
        }

        $missingInTarget = $this->missingColumnsInTarget($source, $targetDatabase, $table);
        if ($missingInTarget !== []) {
            return $empty('Mochi4에 컬럼 없음: '.implode(', ', $missingInTarget));
        }

        $setClauses = $this->buildSetClauses($source, $targetDatabase, $table, $options['protect_empty']);
        if ($setClauses === []) {
            return $empty('동기화할 컬럼 없음');
        }

        $joinSql = $this->buildJoinOn($source, $targetDatabase, $table, $options['join_on']);
        $wouldUpdate = (int) DB::selectOne("
            SELECT COUNT(*) AS c
            FROM `{$source}`.`{$table}` AS m3
            INNER JOIN `{$targetDatabase}`.`{$table}` AS m4 ON {$joinSql}
            WHERE m3.FGC_LastModifyDate > m4.FGC_LastModifyDate
        ")->c;

        $insertStats = $options['insert_missing']
            ? $this->insertMissingRows($table, $targetDatabase, $options['join_on'], $dryRun)
            : ['would_insert' => 0, 'inserted' => 0];

        if ($dryRun) {
            return [
                'table' => $table,
                'would_update' => $wouldUpdate,
                'updated' => 0,
                'would_insert' => $insertStats['would_insert'],
                'inserted' => 0,
                'skipped_reason' => null,
            ];
        }

        $sql = "
            UPDATE `{$targetDatabase}`.`{$table}` AS m4
            INNER JOIN `{$source}`.`{$table}` AS m3 ON {$joinSql}
            SET {$setClauses}
            WHERE m3.FGC_LastModifyDate > m4.FGC_LastModifyDate
        ";

        $updated = DB::affectingStatement($sql);

        return [
            'table' => $table,
            'would_update' => $wouldUpdate,
            'updated' => $updated,
            'would_insert' => $insertStats['would_insert'],
            'inserted' => $insertStats['inserted'],
            'skipped_reason' => null,
        ];
    }

    /**
     * Mochi3에만 있는 행(ID 기준)을 Mochi4에 추가.
     *
     * @return array{would_insert: int, inserted: int}
     */
    private function insertMissingRows(string $table, string $targetDatabase, array $joinColumns, bool $dryRun): array
    {
        $source = self::SOURCE_DATABASE;
        $columns = $this->insertableColumns($source, $targetDatabase, $table);

        if ($columns === []) {
            return ['would_insert' => 0, 'inserted' => 0];
        }

        $existsSql = $this->buildExistsOn('m4', 'm3', $joinColumns);

        $wouldInsert = (int) DB::selectOne("
            SELECT COUNT(*) AS c
            FROM `{$source}`.`{$table}` AS m3
            WHERE NOT EXISTS (
                SELECT 1 FROM `{$targetDatabase}`.`{$table}` AS m4 WHERE {$existsSql}
            )
        ")->c;

        if ($dryRun || $wouldInsert === 0) {
            return ['would_insert' => $wouldInsert, 'inserted' => 0];
        }

        $columnList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
        $selectList = implode(', ', array_map(static fn (string $c): string => "m3.`{$c}`", $columns));

        $inserted = DB::affectingStatement("
            INSERT INTO `{$targetDatabase}`.`{$table}` ({$columnList})
            SELECT {$selectList}
            FROM `{$source}`.`{$table}` AS m3
            WHERE NOT EXISTS (
                SELECT 1 FROM `{$targetDatabase}`.`{$table}` AS m4 WHERE {$existsSql}
            )
        ");

        return ['would_insert' => $wouldInsert, 'inserted' => $inserted];
    }

    /**
     * @param  list<string>  $joinColumns
     */
    private function buildExistsOn(string $leftAlias, string $rightAlias, array $joinColumns): string
    {
        return implode(' AND ', array_map(
            static fn (string $column): string => "{$leftAlias}.`{$column}` = {$rightAlias}.`{$column}`",
            $joinColumns,
        ));
    }

    /**
     * INSERT용 공통 컬럼(ID 포함).
     *
     * @return list<string>
     */
    private function insertableColumns(string $source, string $target, string $table): array
    {
        $sourceColumns = $this->columnNames($source, $table);
        $targetColumns = $this->columnNames($target, $table);
        $exclude = array_flip(['FGC_Rowversion']);

        $columns = [];
        foreach ($sourceColumns as $column) {
            if (isset($exclude[$column])) {
                continue;
            }

            if (in_array($column, $targetColumns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>  $joinColumns
     */
    private function buildJoinOn(string $source, string $target, string $table, array $joinColumns): string
    {
        $parts = [];
        foreach ($joinColumns as $column) {
            if (! $this->columnExists($source, $table, $column) || ! $this->columnExists($target, $table, $column)) {
                throw new RuntimeException("JOIN 컬럼 {$table}.{$column} 이(가) Mochi3/Mochi4 중 한쪽에 없습니다.");
            }
            $parts[] = "m3.`{$column}` = m4.`{$column}`";
        }

        return implode(' AND ', $parts);
    }

    /**
     * @param  list<string>  $protectEmpty
     */
    private function buildSetClauses(string $source, string $target, string $table, array $protectEmpty): string
    {
        $columns = $this->commonDataColumns($source, $target, $table);
        $protect = array_flip($protectEmpty);
        $sets = [];

        foreach ($columns as $column) {
            if (isset($protect[$column])) {
                $sets[] = "m4.`{$column}` = COALESCE(NULLIF(TRIM(m3.`{$column}`), ''), m4.`{$column}`)";

                continue;
            }

            $sets[] = "m4.`{$column}` = m3.`{$column}`";
        }

        return implode(', ', $sets);
    }

    /**
     * @return list<string>
     */
    private function commonDataColumns(string $source, string $target, string $table): array
    {
        $sourceColumns = $this->columnNames($source, $table);
        $targetColumns = $this->columnNames($target, $table);
        $joinOn = self::TABLE_OPTIONS[$table]['join_on'] ?? ['ID'];
        $exclude = array_flip(array_merge(['FGC_Rowversion'], $joinOn));

        $common = [];
        foreach ($sourceColumns as $column) {
            if (isset($exclude[$column])) {
                continue;
            }

            if (in_array($column, $targetColumns, true)) {
                $common[] = $column;
            }
        }

        return $common;
    }

    /**
     * Mochi3에 있으나 Mochi4에 없는 데이터 컬럼(동기화 불가).
     *
     * @return list<string>
     */
    private function missingColumnsInTarget(string $source, string $target, string $table): array
    {
        $sourceColumns = $this->columnNames($source, $table);
        $targetColumns = $this->columnNames($target, $table);
        $exclude = array_flip(['ID', 'FGC_Rowversion']);
        $missing = [];

        foreach ($sourceColumns as $column) {
            if (isset($exclude[$column])) {
                continue;
            }

            if (! in_array($column, $targetColumns, true)) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    private function hasModifyDateColumn(string $database, string $table): bool
    {
        return $this->columnExists($database, $table, 'FGC_LastModifyDate');
    }

    private function columnExists(string $database, string $table, string $column): bool
    {
        return DB::selectOne('
            SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$database, $table, $column])->c > 0;
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $database, string $table): array
    {
        $rows = DB::select('
            SELECT COLUMN_NAME AS name
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ', [$database, $table]);

        return array_map(static fn ($row): string => (string) $row->name, $rows);
    }

    private function tableExistsInDatabase(string $database, string $table): bool
    {
        return DB::selectOne('
            SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ', [$database, $table])->c > 0;
    }

    private function assertDatabasesExist(string $targetDatabase): void
    {
        if (! $this->databaseExists(self::SOURCE_DATABASE)) {
            throw new RuntimeException('Mochi3 데이터베이스를 찾을 수 없습니다.');
        }

        if (! $this->databaseExists($targetDatabase)) {
            throw new RuntimeException("대상 데이터베이스 {$targetDatabase} 를 찾을 수 없습니다.");
        }

        if ($targetDatabase === self::SOURCE_DATABASE) {
            throw new RuntimeException('소스(Mochi3)와 대상 DB가 같습니다. .env DB_DATABASE 를 Mochi4 로 설정하세요.');
        }
    }

    private function databaseExists(string $database): bool
    {
        return DB::selectOne('
            SELECT COUNT(*) AS c
            FROM information_schema.SCHEMATA
            WHERE SCHEMA_NAME = ?
        ', [$database])->c > 0;
    }
}
