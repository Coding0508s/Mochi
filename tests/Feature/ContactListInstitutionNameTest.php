<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class ContactListInstitutionNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
    }

    private function createContactTables(): void
    {
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 255)->nullable();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('Email', 190)->nullable();
            $table->string('Phone', 190)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->string('Status', 50)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->dateTime('Created_Date')->nullable();
        });
    }

    public function test_select_teacher_institution_uses_account_information_name_first(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SSOT-CONTACT-1',
            'AccountName' => '레거시 연락처 기관명',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-SSOT-CONTACT-1',
            'Account_Name' => '마스터 연락처 기관명',
        ]);

        Teacher::query()->create([
            'SK_Code' => 'SK-SSOT-CONTACT-1',
            'Name' => '테스트 교사',
            'Email' => 'teacher@example.com',
            'ClassInOut' => true,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('selectTeacherInstitution', 'SK-SSOT-CONTACT-1')
            ->assertSet('newSkCode', 'SK-SSOT-CONTACT-1')
            ->assertSet('newSchoolName', '마스터 연락처 기관명');
    }

    public function test_exports_contacts_to_excel_with_current_filters(): void
    {
        Teacher::query()->create([
            'SK_Code' => '0123456789',
            'Name' => '엑셀 테스트 교사',
            'Email' => 'export-contact@example.com',
            'Phone' => '01099998888',
            'School_Name' => '엑셀 테스트 기관',
            'ClassInOut' => true,
        ]);

        $now = now();
        Carbon::setTestNow($now);

        $component = Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(ContactList::class)
            ->set('searchType', 'name')
            ->set('search', '엑셀 테스트')
            ->call('exportContactsExcel')
            ->assertFileDownloaded('교직원_연락처_'.$now->format('Ymd_His').'.xlsx');

        $xlsxBinary = base64_decode((string) data_get($component->effects, 'download.content'), true);
        $this->assertNotFalse($xlsxBinary);
        $this->assertNotSame('', $xlsxBinary);

        $tempPath = tempnam(sys_get_temp_dir(), 'contact-export-').'.xlsx';
        file_put_contents($tempPath, $xlsxBinary);

        try {
            $sheet = IOFactory::load($tempPath)->getActiveSheet();

            $skCodeCell = $sheet->getCell('A2');
            $phoneCell = $sheet->getCell('F2');

            $this->assertSame(DataType::TYPE_STRING, $skCodeCell->getDataType());
            $this->assertSame('0123456789', $skCodeCell->getValue());
            $this->assertSame(NumberFormat::FORMAT_TEXT, $skCodeCell->getStyle()->getNumberFormat()->getFormatCode());

            $this->assertSame(DataType::TYPE_STRING, $phoneCell->getDataType());
            $this->assertSame('01099998888', $phoneCell->getValue());
            $this->assertSame(NumberFormat::FORMAT_TEXT, $phoneCell->getStyle()->getNumberFormat()->getFormatCode());
        } finally {
            @unlink($tempPath);
        }
    }
}
