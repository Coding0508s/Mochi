<?php

namespace App\Support;

use App\Models\AccountInformation;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S_Account_Information 행 + S_AccountName 에만 있는 기관(SK)을 합친 목록.
 *
 * phpMyAdmin 등에서 "400건 이상"으로 보이는 경우는 보통
 * 두 테이블의 SK 합집합(개발 DB 기준 401)에 가깝습니다.
 */
final class InstitutionCatalog
{
    public static function query(): Builder
    {
        if (! Schema::hasTable('S_Account_Information') || ! Schema::hasTable('S_AccountName')) {
            return AccountInformation::query();
        }

        $query = AccountInformation::query()->fromSub(
            self::unionQuery(),
            'catalog',
        );

        $query->getModel()->setTable('catalog');

        return $query;
    }

    public static function unionQuery(): QueryBuilder
    {
        $information = DB::table('S_Account_Information')->select(self::informationSelectColumns());

        $masterOnly = DB::table('S_AccountName as m')
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->select(DB::raw('1'))
                    ->from('S_Account_Information as i')
                    ->whereColumn('i.SK_Code', 'm.SKcode');
            })
            ->select(self::masterOnlySelectColumns());

        if (Schema::hasTable('S_GSNumber')) {
            $masterOnly->leftJoin('S_GSNumber as g', 'm.SKcode', '=', 'g.SKCode');
        }

        return $information->unionAll($masterOnly);
    }

    /**
     * @return list<string|Expression>
     */
    private static function informationSelectColumns(): array
    {
        $columns = [
            'ID',
            'SK_Code',
            'Account_Name',
            'TR',
            'CS',
            'CO',
            'Customer_Type',
            'Affiliate',
            'Address',
        ];

        $columns[] = Schema::hasColumn('S_Account_Information', 'FGC_CreateDate')
            ? 'FGC_CreateDate'
            : DB::raw('NULL as FGC_CreateDate');

        $columns[] = DB::raw('0 as is_master_only');

        return $columns;
    }

    /**
     * @return list<string|Expression>
     */
    private static function masterOnlySelectColumns(): array
    {
        $hasGsNumber = Schema::hasTable('S_GSNumber');

        $columns = [
            'm.ID as ID',
            'm.SKcode as SK_Code',
            'm.AccountName as Account_Name',
            $hasGsNumber ? 'g.TR as TR' : DB::raw('NULL as TR'),
            $hasGsNumber ? 'g.CS as CS' : DB::raw('NULL as CS'),
            $hasGsNumber ? 'g.CO as CO' : DB::raw('NULL as CO'),
            DB::raw('NULL as Customer_Type'),
            DB::raw('NULL as Affiliate'),
            'm.Address as Address',
        ];

        $columns[] = Schema::hasColumn('S_AccountName', 'FGC_CreateDate')
            ? 'm.FGC_CreateDate as FGC_CreateDate'
            : DB::raw('NULL as FGC_CreateDate');

        $columns[] = DB::raw('1 as is_master_only');

        return $columns;
    }
}
