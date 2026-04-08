<?php

namespace Database\Seeders;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadHoldReason;
use App\Models\LeadLostReason;
use App\Models\LeadSource;
use App\Models\InstitutionType;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadDemoSeeder extends Seeder
{
    public function run(): void
    {
        $source = LeadSource::query()->firstOrCreate(
            ['code' => 'web'],
            ['name' => '웹 문의', 'active' => true, 'sort_order' => 10]
        );

        $type = InstitutionType::query()->firstOrCreate(
            ['code' => 'kinder'],
            ['name' => '유치원', 'active' => true, 'sort_order' => 10]
        );

        $region = Region::query()->firstOrCreate(
            ['code' => 'seoul'],
            ['name' => '서울', 'active' => true, 'sort_order' => 10, 'parent_id' => null]
        );

        LeadLostReason::query()->firstOrCreate(
            ['name' => '예산 부족'],
            ['active' => true, 'sort_order' => 10]
        );

        LeadHoldReason::query()->firstOrCreate(
            ['name' => '내부 검토 대기'],
            ['active' => true, 'sort_order' => 10]
        );

        $owner = User::query()->orderBy('id')->first();
        if (! $owner) {
            return;
        }

        if (Lead::query()->exists()) {
            return;
        }

        Lead::query()->create([
            'institution_name' => '데모 유치원 A',
            'status' => LeadStatus::Active,
            'stage' => LeadStage::Proposal,
            'lead_source_id' => $source->id,
            'institution_type_id' => $type->id,
            'region_id' => $region->id,
            'owner_user_id' => $owner->id,
            'registered_by_user_id' => $owner->id,
            'interest_level' => 4,
            'priority_level' => 2,
            'lead_score' => 72,
            'next_action_date' => now()->addDays(3)->toDateString(),
            'next_action_type' => '전화',
            'next_action_note' => '견적 피드백 확인',
        ]);

        Lead::query()->create([
            'institution_name' => '데모 초등 B',
            'status' => LeadStatus::Active,
            'stage' => LeadStage::InitialContact,
            'owner_user_id' => $owner->id,
            'registered_by_user_id' => $owner->id,
        ]);
    }
}
