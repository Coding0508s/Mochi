<?php

namespace Tests\Feature;

use App\Services\Store\EcountApiClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EcountApiClientSaveSaleOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.zone', '');
        Config::set('store.ecount.session_id', 'test-session');
        Config::set('store.ecount.auto_login_when_empty_session', false);
        Config::set('store.ecount.cache_ttl_seconds', 300);
        Config::set('store.return_registration.sale_order_endpoint', '/OAPI/V2/SaleOrder/SaveSaleOrder');
        Config::set('store.timeout', 5);
    }

    public function test_save_sale_order_returns_slip_nos_on_success(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SuccessCnt' => 1,
                    'FailCnt' => 0,
                    'SlipNos' => ['20260708-21'],
                    'ResultDetails' => [['IsSuccess' => true]],
                ],
                'Error' => null,
            ], 200),
        ]);

        $body = [
            'SaleOrderList' => [
                ['BulkDatas' => ['PROD_CD' => 'J11S-SSET-400', 'QTY' => '-1']],
            ],
        ];

        $result = app(EcountApiClient::class)->saveSaleOrder($body);

        $this->assertSame(['20260708-21'], $result['slip_nos']);
        $this->assertSame('200', $result['raw']['Status']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'SESSION_ID=test-session')
                && str_contains($request->url(), 'SaveSaleOrder');
        });
    }

    public function test_save_sale_order_normalizes_single_slip_no_string(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SuccessCnt' => 1,
                    'FailCnt' => 0,
                    'SlipNos' => '20260708-22',
                ],
                'Error' => null,
            ], 200),
        ]);

        $result = app(EcountApiClient::class)->saveSaleOrder([
            'SaleOrderList' => [['BulkDatas' => ['PROD_CD' => 'X']]],
        ]);

        $this->assertSame(['20260708-22'], $result['slip_nos']);
    }

    public function test_save_sale_order_throws_when_fail_cnt_positive(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SuccessCnt' => 0,
                    'FailCnt' => 1,
                    'SlipNos' => [],
                ],
                'Error' => null,
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ecount 주문서 생성');

        app(EcountApiClient::class)->saveSaleOrder([
            'SaleOrderList' => [['BulkDatas' => ['PROD_CD' => 'X']]],
        ]);
    }

    public function test_save_sale_order_throws_when_slip_nos_empty(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SuccessCnt' => 1,
                    'FailCnt' => 0,
                    'SlipNos' => null,
                ],
                'Error' => null,
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ecount 주문서 생성');

        app(EcountApiClient::class)->saveSaleOrder([
            'SaleOrderList' => [['BulkDatas' => ['PROD_CD' => 'X']]],
        ]);
    }

    public function test_save_sale_order_throws_on_api_status_failure(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '500',
                'Data' => null,
                'Error' => ['Message' => '서버 오류'],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('서버 오류');

        app(EcountApiClient::class)->saveSaleOrder([
            'SaleOrderList' => [['BulkDatas' => ['PROD_CD' => 'X']]],
        ]);
    }

    public function test_save_sale_order_does_not_cache_response(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::sequence()
                ->push([
                    'Status' => '200',
                    'Data' => [
                        'SuccessCnt' => 1,
                        'FailCnt' => 0,
                        'SlipNos' => ['slip-1'],
                    ],
                    'Error' => null,
                ])
                ->push([
                    'Status' => '200',
                    'Data' => [
                        'SuccessCnt' => 1,
                        'FailCnt' => 0,
                        'SlipNos' => ['slip-2'],
                    ],
                    'Error' => null,
                ]),
        ]);

        $client = app(EcountApiClient::class);
        $body = ['SaleOrderList' => [['BulkDatas' => ['PROD_CD' => 'X']]]];

        $first = $client->saveSaleOrder($body);
        $second = $client->saveSaleOrder($body);

        $this->assertSame(['slip-1'], $first['slip_nos']);
        $this->assertSame(['slip-2'], $second['slip_nos']);
    }
}
