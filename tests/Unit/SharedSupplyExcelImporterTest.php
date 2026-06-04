<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\User;
use App\Models\VehicleUsageLog;
use App\Support\SharedSupplyExcelImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SharedSupplyExcelImporterTest extends TestCase
{
    use RefreshDatabase;

    private function baseRows(): array
    {
        return [
            ['회사명 그레이프시드코리아 주식회사 2026/06/02 ~ 2026/06/30'],
            ['일자', '시작시간', '종료시간', '물품명', '사용자명', '제목', '적요'],
        ];
    }

    public function test_import_inserts_new_rows(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        $rows = array_merge($this->baseRows(), [
            ['2026/06/02', '09:00', '16:00', $item->name, '최인지', '[차량배차] 신청 및 예약', '어린이집 출장'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => $item->id,
            'title' => '[차량배차] 신청 및 예약',
        ]);
    }

    public function test_import_updates_existing_duplicate_row(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $labelId = (int) \DB::table('shared_supply_labels')->where('code', '01')->value('id');

        $existing = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-02 09:00:00',
            'ends_at' => '2026-06-02 16:00:00',
            'shared_supply_item_id' => $item->id,
            'shared_supply_label_id' => $labelId,
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => '기존 적요',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $rows = array_merge($this->baseRows(), [
            ['2026/06/02', '9:00', '16:00', $item->name, '최인지', '[차량배차] 신청 및 예약', '업데이트 적요'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $existing->refresh();
        $this->assertSame('업데이트 적요', $existing->purpose);
    }

    public function test_import_supports_24_00_as_next_day_midnight(): void
    {
        $user = User::factory()->create(['name' => '신혜영']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00013')->firstOrFail();

        $rows = array_merge($this->baseRows(), [
            ['2026/06/04', '00:00', '24:00', $item->name, '신혜영', '[회의실] 신청 및 예약', '종일 테스트'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);

        $record = SharedSupply::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-06-04 00:00', $record->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-06-05 00:00', $record->ends_at->format('Y-m-d H:i'));
    }

    public function test_import_matches_user_by_inside_parenthesis_name(): void
    {
        $user = User::factory()->create(['name' => 'Becky Choi']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        $rows = array_merge($this->baseRows(), [
            ['2026/06/02 (화)', '09:00', '16:00', $item->name, '최인지(Becky Choi)', '[차량배차] 신청 및 예약', '어린이집 출장'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => $item->id,
        ]);
    }

    public function test_import_ignores_footer_like_rows_without_key_fields(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        $rows = array_merge($this->baseRows(), [
            ['2026/06/02', '09:00', '16:00', $item->name, '최인지', '[차량배차] 신청 및 예약', '어린이집 출장'],
            ['2026/06/02 14:50:22', '', '', '', '', '', ''],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);
    }

    public function test_import_creates_user_when_only_employee_exists(): void
    {
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('KOREANAME')->nullable();
                $table->string('ENGLISHNAME')->nullable();
                $table->string('EMAIL')->nullable();
            });
        }

        Employee::query()->create([
            'EMPNO' => 'E-2001',
            'KOREANAME' => '김건모',
            'ENGLISHNAME' => 'Daniel Kim',
            'EMAIL' => 'daniel.kim@example.com',
        ]);

        $rows = array_merge($this->baseRows(), [
            ['2026/06/16', '09:00', '10:00', $item->name, '김건모(Daniel Kim)', '[차량배차] 신청 및 예약', 'employee만 존재'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $createdUser = User::query()->where('employee_empno', 'E-2001')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('김건모', $createdUser->name);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $createdUser->id,
            'shared_supply_item_id' => $item->id,
            'title' => '[차량배차] 신청 및 예약',
        ]);
    }

    public function test_import_supports_schedule_export_format_and_maps_place_to_purpose(): void
    {
        $user = User::factory()->create(['name' => 'Ellie Lee']);
        $actor = User::factory()->create();

        SharedSupplyItem::query()->where('name', '연차휴가')->delete();

        $rows = [
            ['회사명 : 그레이프시드코리아 주식회사 / 2026/06/02 ~ 2026/06/30'],
            ['일자(요일)', '시작시간', '종료시간', '작성자명', '제목', '장소'],
            ['2026/06/02 (화)', '08:30', '12:00', '이주연(Ellie Lee)', '[휴가]오전 반차', '태아 정기 검진'],
            ['2026/06/04 (목)', '00:00', '24:00', '이주연(Ellie Lee)', '[휴가]연차휴가', '개인 일정'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'title' => '[휴가]오전 반차',
            'purpose' => '태아 정기 검진',
            'schedule_category_code' => '001',
        ]);

        $this->assertDatabaseHas('shared_supply_items', [
            'name' => '연차휴가',
            'is_active' => true,
        ]);

        $annualLeaveItem = SharedSupplyItem::query()->where('name', '연차휴가')->firstOrFail();
        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => $annualLeaveItem->id,
            'title' => '[휴가]연차휴가',
            'purpose' => '개인 일정',
        ]);
    }

    public function test_import_file_scans_all_sheets_and_fuzzy_header_names(): void
    {
        $user = User::factory()->create(['name' => 'Ellie Lee']);
        $actor = User::factory()->create();

        $spreadsheet = new Spreadsheet;
        $blankSheet = $spreadsheet->getActiveSheet();
        $blankSheet->setTitle('blank');
        $blankSheet->setCellValue('A1', 'empty');

        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('schedule');
        $dataSheet->fromArray([
            ['회사명 : 그레이프시드코리아 주식회사 / 2026/06/02 ~ 2026/06/30'],
            [' 일자 (요일) ', '시작 시간', '종료 시간', '참석자성명', '제목', '장소'],
            ['2026/06/02 (화)', '08:30', '12:00', '이주연(Ellie Lee)', '[휴가]연차휴가', '태아 정기 검진'],
        ]);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'shared-supply-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(SharedSupplyExcelImporter::class)->importFromFile($path, $actor->id);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'title' => '[휴가]연차휴가',
            'purpose' => '태아 정기 검진',
        ]);
    }

    public function test_import_supports_vehicle_log_export_headers_without_time_and_title_columns(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $labelId = (int) \DB::table('shared_supply_labels')->where('code', '01')->value('id');

        $rows = [
            ['일정No.', '사용자명', '이동수단명', '사용목적명', '주행전 계기판거리', '주행후 계기판거리', '운행거리', '적요'],
            ['2026/06/02 -4', '최인지', $item->name, '일반업무', '56467', '56592', '125', 'B2/B16 이천 어린왕자어린이집'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $record = SharedSupply::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($item->id, (int) $record->shared_supply_item_id);
        $this->assertSame($labelId, (int) $record->shared_supply_label_id);
        $this->assertSame('[출장 차량배차] 신청 및 예약', $record->title);
        $this->assertSame('일반업무 / B2/B16 이천 어린왕자어린이집', $record->purpose);
        $this->assertSame('2026-06-02', $record->starts_at->format('Y-m-d'));
        $this->assertTrue($record->ends_at->gt($record->starts_at));
    }

    public function test_import_supports_vehicle_log_header_alias_ilja_no(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        $rows = [
            ['일자No.', '사용자명', '이동수단명', '사용목적명', '적요'],
            ['2026/06/02 -4', '최인지', $item->name, '일반업무', 'B2/B16 이천 어린왕자어린이집'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => $item->id,
            'title' => '[출장 차량배차] 신청 및 예약',
        ]);
    }

    public function test_import_matches_vehicle_item_by_plate_number_when_name_spelling_differs(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();

        $rows = [
            ['일자No.', '사용자명', '이동수단명', '사용목적명', '적요'],
            ['2026/06/02 -4', '최인지', '62노5836 (아반떼/경유)', '일반업무', '차량명 철자 흔들림 테스트'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'shared_supply_item_id' => SharedSupplyItem::query()->where('code', '00008')->value('id'),
            'title' => '[출장 차량배차] 신청 및 예약',
        ]);
    }

    public function test_import_vehicle_log_format_creates_vehicle_usage_log_when_columns_exist(): void
    {
        if (! Schema::hasColumn('vehicle_usage_logs', 'shared_supply_id')) {
            Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
                $table->unsignedBigInteger('shared_supply_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('vehicle_name')->nullable();
                $table->string('usage_purpose_name')->nullable();
                $table->unsignedInteger('odometer_before')->nullable();
                $table->unsignedInteger('odometer_after')->nullable();
                $table->unsignedInteger('distance')->nullable();
                $table->string('arrival_location')->nullable();
                $table->string('remarks')->nullable();
                $table->date('driven_on')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
            });
        }

        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00003')->firstOrFail();

        $rows = [
            ['일자No.', '사용자명', '이동수단명', '사용목적명', '주행전 계기판거리', '주행후 계기판거리', '운행거리', '도착위치', '적요'],
            ['2026/06/02 -1', '최인지', $item->name, '일반업무', '74,994', '75,054', '60', '용인 시범센터팀', 'B2 B26 / 용인 시범센터팀 어린이집'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(0, $result['errors']);

        $supply = SharedSupply::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('vehicle_usage_logs', [
            'shared_supply_id' => $supply->id,
            'user_id' => $user->id,
            'vehicle_name' => $item->name,
            'usage_purpose_name' => '일반업무',
            'odometer_before' => 74994,
            'odometer_after' => 75054,
            'distance' => 60,
            'arrival_location' => '용인 시범센터팀',
        ]);
        $this->assertInstanceOf(VehicleUsageLog::class, VehicleUsageLog::query()->where('shared_supply_id', $supply->id)->first());
    }

    public function test_loose_match_updates_when_times_differ_on_same_day(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $labelId = (int) \DB::table('shared_supply_labels')->where('code', '01')->value('id');

        $existing = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-02 09:00:00',
            'ends_at' => '2026-06-02 16:00:00',
            'shared_supply_item_id' => $item->id,
            'shared_supply_label_id' => $labelId,
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => '기존 적요',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $rows = array_merge($this->baseRows(), [
            ['2026/06/02', '10:30', '18:00', $item->name, '최인지', '[차량배차] 신청 및 예약', '시간만 변경'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, SharedSupply::query()->count());

        $existing->refresh();
        $this->assertSame((int) $existing->id, SharedSupply::query()->value('id'));
        $this->assertSame('2026-06-02 10:30', $existing->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-06-02 18:00', $existing->ends_at->format('Y-m-d H:i'));
        $this->assertSame('시간만 변경', $existing->purpose);
    }

    public function test_import_does_not_delete_rows_missing_from_excel(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $labelId = (int) \DB::table('shared_supply_labels')->where('code', '01')->value('id');

        $stale = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-04 09:00:00',
            'ends_at' => '2026-06-04 12:00:00',
            'shared_supply_item_id' => $item->id,
            'shared_supply_label_id' => $labelId,
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => 'UI에서 등록한 건',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $rows = array_merge($this->baseRows(), [
            ['2026/06/12', '09:00', '16:00', $item->name, '최인지', '[차량배차] 신청 및 예약', '엑셀에만 있는 건'],
        ]);

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['deleted']);
        $this->assertDatabaseHas('shared_supplies', ['id' => $stale->id]);
        $this->assertDatabaseHas('shared_supplies', [
            'user_id' => $user->id,
            'purpose' => '엑셀에만 있는 건',
        ]);
    }

    public function test_vehicle_schedule_reimport_updates_without_duplicate(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();

        $rows = [
            ['일정No.', '사용자명', '이동수단명', '사용목적명', '주행전 계기판거리', '주행후 계기판거리', '운행거리', '적요'],
            ['2026/06/02 -4', '최인지', $item->name, '일반업무', '56467', '56592', '125', '첫 반영'],
        ];

        $first = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);
        $this->assertSame(1, $first['inserted']);

        $rows[1][6] = '130';
        $rows[1][7] = '두 번째 반영';
        $second = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(0, $second['inserted']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, SharedSupply::query()->where('user_id', $user->id)->count());

        $log = VehicleUsageLog::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(130, (int) $log->distance);
        $this->assertStringContainsString('[excel-schedule:2026/06/02 -4]', (string) $log->remarks);
    }

    public function test_vehicle_log_import_updates_existing_vehicle_schedule_even_when_title_differs(): void
    {
        $user = User::factory()->create(['name' => '최인지']);
        $actor = User::factory()->create();
        $item = SharedSupplyItem::query()->where('code', '00008')->firstOrFail();
        $labelId = (int) \DB::table('shared_supply_labels')->where('code', '01')->value('id');

        $existing = SharedSupply::query()->create([
            'user_id' => $user->id,
            'starts_at' => '2026-06-02 09:00:00',
            'ends_at' => '2026-06-02 16:00:00',
            'shared_supply_item_id' => $item->id,
            'shared_supply_label_id' => $labelId,
            'title' => '[차량배차] 신청 및 예약',
            'purpose' => '기존 차량 일정',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $rows = [
            ['일정No.', '사용자명', '이동수단명', '사용목적명', '주행전 계기판거리', '주행후 계기판거리', '운행거리', '적요'],
            ['2026/06/02 -4', '최인지', $item->name, '일반업무', '56467', '56592', '125', '차량 로그 반영'],
        ];

        $result = app(SharedSupplyExcelImporter::class)->importRows($rows, $actor->id);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, SharedSupply::query()->where('user_id', $user->id)->count());

        $existing->refresh();
        $this->assertSame('[출장 차량배차] 신청 및 예약', $existing->title);
        $this->assertSame('일반업무 / 차량 로그 반영', $existing->purpose);
    }
}
