<?php

namespace Tests\Feature;

use App\Models\Institution;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportSchoolMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('S_AccountName');
        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('EnglishName', 255)->nullable();
            $table->string('PortalAccountName', 255)->nullable();
            $table->string('PortalCampusID', 100)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->string('GSno', 100)->nullable();
            $table->string('Director', 255)->nullable();
            $table->string('Phone', 100)->nullable();
            $table->string('AccountTel', 100)->nullable();
            $table->string('Address', 255)->nullable();
            $table->string('Gubun', 100)->nullable();
        });
    }

    public function test_updates_only_portal_campus_id_when_sk_exists_case_insensitive(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CASE-1',
            'AccountName' => '원래 기관명',
            'PortalCampusID' => null,
        ]);

        $path = $this->writeSchoolMasterXlsx([
            ['SchoolCode', 'PortalCampusID', 'AccountName'],
            ['sk-case-1', 'PORTAL-99', '엑셀에서 바꾼 이름'],
        ]);

        $exit = Artisan::call('import:school-master', ['file' => $path]);
        $this->assertSame(0, $exit);

        $row = Institution::query()->where('SKcode', 'SK-CASE-1')->first();
        $this->assertNotNull($row);
        $this->assertSame('PORTAL-99', $row->PortalCampusID);
        $this->assertSame('원래 기관명', $row->AccountName);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeSchoolMasterXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'mst').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
