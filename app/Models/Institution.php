<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

/**
 * ═══════════════════════════════════════════════════════════════
 * [기관 정보] Institution 모델
 * ───────────────────────────────────────────────────────────────
 * 데이터베이스 테이블: S_AccountName
 *
 * 이 모델 하나로 "기관"이라는 개념을 코드에서 표현합니다.
 * 예를 들어:
 *   $institution = Institution::find(1);
 *   echo $institution->AccountName;  // "○○유치원"
 *   $institution->teachers;          // 이 기관 소속 교사 목록
 *   $institution->supportRecords;    // 이 기관의 모든 지원 내역
 * ═══════════════════════════════════════════════════════════════
 *
 * @property int    $ID
 * @property string $SKcode          기관 SK 코드 (업무상 핵심 식별자)
 * @property string $AccountName     기관명(한글)
 * @property string $EnglishName     기관명(영문)
 * @property string $PortalAccountName 포털 표시 기관명
 * @property string $AccountNo       사업자/기관 번호
 * @property string $GSno            GrapeSEED 번호
 * @property string $Director        원장명
 * @property string $Phone           대표 전화번호
 * @property string $AccountTel      직통 연락처
 * @property string $Address         주소
 * @property string $Gubun           구분(유치원/초등 등)
 */
class Institution extends Model
{
    // ─── 테이블 설정 ──────────────────────────────────────────────────
    protected $table = 'S_AccountName';
    // Laravel이 기본으로 찾는 테이블명(institutions)이 아니라
    // 기존 SQL의 이름(S_AccountName)을 직접 지정합니다.

    protected $primaryKey = 'ID';
    // 기본 키가 id가 아니라 대문자 ID임을 명시합니다.

    public $timestamps = false;
    // created_at / updated_at 컬럼이 없으므로 자동 처리를 끕니다.
    // (Forguncy의 FGC_CreateDate, FGC_LastModifyDate 가 그 역할을 대신합니다.)

    // ─── 대량 입력 허용 필드 (보안 설정) ───────────────────────────────
    // $fillable 에 적힌 컬럼만 한꺼번에 저장(create/update) 할 수 있습니다.
    // 적혀 있지 않은 컬럼은 직접 하나씩 지정해야만 바꿀 수 있어 안전합니다.
    protected $fillable = [
        'SKcode',
        'AccountName',
        'EnglishName',
        'PortalAccountName',
        'AccountNo',
        'GSno',
        'Director',
        'Phone',
        'AccountTel',
        'Address',
        'Gubun',
    ];

    // ─── 날짜/타입 자동 변환 설정 ────────────────────────────────────
    // 아래 필드는 DB에서 꺼내올 때 자동으로 PHP의 날짜 객체로 바뀝니다.
    // 예: $institution->FGC_CreateDate->format('Y-m-d') 처럼 쓸 수 있습니다.
    protected function casts(): array
    {
        return [
            'FGC_CreateDate'     => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'FGC_Rowversion'     => 'datetime',
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // 관계(Relationship) 정의
    // ════════════════════════════════════════════════════════════════

    /**
     * 이 기관에 배정된 담당자(TR/CS/CO) 정보
     *
     * 사용 예:
     *   $institution->accountInfo->CO  // 담당 CO 이름
     */
    public function accountInfo(): HasOne
    {
        return $this->hasOne(AccountInformation::class, 'SK_Code', 'SKcode');
        // "S_Account_Information 테이블에서 SK_Code = 이 기관의 SKcode 인 행을 가져와"
    }

    /**
     * 이 기관에 속한 교사(연락처) 목록
     *
     * 사용 예:
     *   $institution->teachers          // 교사 Collection
     *   $institution->teachers->count() // 교사 수
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class, 'SK_Code', 'SKcode');
        // "Teachers 테이블에서 SK_Code = 이 기관의 SKcode 인 행들을 가져와"
    }

    /**
     * 이 기관의 모든 지원 내역 (지원 방문/전화 기록)
     *
     * 사용 예:
     *   $institution->supportRecords                     // 전체 내역
     *   $institution->supportRecords()->where('Year', 2025)->get() // 2025년 내역만
     */
    public function supportRecords(): HasMany
    {
        return $this->hasMany(SupportRecord::class, 'SK_Code', 'SKcode');
    }

    // ════════════════════════════════════════════════════════════════
    // 검색용 스코프 (자주 쓰는 조건 묶음)
    // ════════════════════════════════════════════════════════════════

    /**
     * 기관명 또는 SKcode로 검색
     *
     * 사용 예:
     *   Institution::search('○○유치원')->get()
     *   Institution::search('SK001')->get()
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        // "서울 강남"으로 검색해도 "서울강남" 데이터를 찾을 수 있도록
        // 검색어/DB 값 모두 공백을 제거해 비교합니다.
        $normalizedKeyword = preg_replace('/\s+/u', '', (string) $keyword) ?? '';

        if ($normalizedKeyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($normalizedKeyword) {
            $q->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
              ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"])
              ->orWhereRaw("REPLACE(Director, ' ', '') like ?", ["%{$normalizedKeyword}%"])
              ->orWhereRaw("REPLACE(Address, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
        });
    }

    /**
     * 구분(기관 종류)으로 필터
     *
     * 사용 예:
     *   Institution::ofType('유치원')->get()
     */
    public function scopeOfType(Builder $query, ?string $gubun): Builder
    {
        if (blank($gubun)) {
            return $query;
        }

        return $query->where('Gubun', $gubun);
    }
}
