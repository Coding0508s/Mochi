<?php

namespace Tests\Feature;

use App\Livewire\PotentialInstitutionView;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PotentialInstitutionViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPotentialInstitutionTables();
    }

    private function createPotentialInstitutionTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 150);
            $table->string('Address', 255)->nullable();
            $table->string('Director', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Connected', 100)->nullable();
            $table->string('Type', 100);
            $table->string('Gubun', 100);
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->integer('Approaching')->default(0);
            $table->integer('Presenting')->default(0);
            $table->integer('Consulting')->default(0);
            $table->integer('Closing')->default(0);
            $table->integer('DroppedOut')->default(0);
            $table->boolean('IsContract')->default(false);
            $table->date('ContractedDate')->nullable();
            $table->string('Possibility', 20)->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('MeetingTime', 20)->nullable();
            $table->string('MeetingTime_End', 20)->nullable();
            $table->text('Description')->nullable();
            $table->string('ConsultingType', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });
    }

    public function test_created_mode_lists_only_targets_in_selected_month(): void
    {
        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-15',
            'AccountName' => 'AprilOnly',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 1,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 1,
        ]);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-05-02',
            'AccountName' => 'MayOnly',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 1,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 1,
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->assertSee('AprilOnly')
            ->assertDontSee('MayOnly');
    }

    public function test_meeting_mode_lists_only_details_in_selected_month(): void
    {
        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'MeetingApril',
            'MeetingDate' => '2026-04-08',
            'ConsultingType' => '콜',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'MeetingJune',
            'MeetingDate' => '2026-06-01',
            'ConsultingType' => '방문',
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'meeting')
            ->assertSee('MeetingApril')
            ->assertDontSee('MeetingJune');
    }

    public function test_year_mode_lists_targets_across_selected_year(): void
    {
        CoNewTarget::query()->create([
            'Year' => 2025,
            'CreatedDate' => '2025-11-20',
            'AccountName' => 'Year2025Row',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
        ]);

        CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-03-10',
            'AccountName' => 'Year2026Row',
            'Type' => '신규',
            'Gubun' => '방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('periodGranularity', 'year')
            ->set('filterYear', '2026')
            ->set('dateBasis', 'created')
            ->assertSee('Year2026Row')
            ->assertDontSee('Year2025Row');
    }

    public function test_year_mode_meeting_lists_details_in_selected_year(): void
    {
        CoNewTargetDetail::query()->create([
            'Year' => 2025,
            'AccountName' => 'M2025',
            'MeetingDate' => '2025-12-01',
            'ConsultingType' => '콜',
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => 'M2026',
            'MeetingDate' => '2026-02-15',
            'ConsultingType' => '방문',
        ]);

        Livewire::test(PotentialInstitutionView::class)
            ->set('periodGranularity', 'year')
            ->set('filterYear', '2026')
            ->set('dateBasis', 'meeting')
            ->assertSee('M2026')
            ->assertDontSee('M2025');
    }

    public function test_switching_year_month_resets_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            CoNewTarget::query()->create([
                'Year' => 2026,
                'CreatedDate' => '2026-04-'.str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT),
                'AccountName' => 'Bulk '.$i,
                'Type' => '신규',
                'Gubun' => '방문',
                'LS' => 0,
                'GS_K' => 0,
                'GS_E' => 0,
                'Total' => 0,
            ]);
        }

        $component = Livewire::test(PotentialInstitutionView::class)
            ->set('yearMonth', '2026-04')
            ->set('dateBasis', 'created')
            ->call('gotoPage', 2);

        $component->set('yearMonth', '2026-05')
            ->assertSet('paginators.page', 1);
    }

    public function test_route_potential_institutions_view_is_registered(): void
    {
        $this->assertSame(
            url('/potential-institutions/view'),
            route('potential-institutions.view'),
        );
    }
}
