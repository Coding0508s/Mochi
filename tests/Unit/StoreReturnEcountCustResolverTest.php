<?php

namespace Tests\Unit;

use App\Models\InstitutionExternalMapping;
use App\Support\StoreReturnEcountCustResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StoreReturnEcountCustResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_cust_from_erp_account_no(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '울산 북구 연세케이잉글리쉬',
            'account_no' => 'X',
            'sk_code' => 'SK-TEST-1',
            'erp_institution_name' => '연세케이윙글리쉬',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $result = app(StoreReturnEcountCustResolver::class)
            ->resolve('SK-TEST-1', 'fallback name');

        $this->assertSame('1069626354', $result['cust']);
        $this->assertSame('연세케이윙글리쉬', $result['cust_des']);
    }

    public function test_throws_when_sk_missing_or_erp_account_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(StoreReturnEcountCustResolver::class)->resolve(null, '이름만');
    }
}
