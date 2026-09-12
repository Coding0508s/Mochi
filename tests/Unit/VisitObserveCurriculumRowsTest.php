<?php

namespace Tests\Unit;

use App\Support\VisitObserveCurriculumRows;
use PHPUnit\Framework\TestCase;

class VisitObserveCurriculumRowsTest extends TestCase
{
    public function test_default_rows_are_empty_until_a_curriculum_is_chosen(): void
    {
        $this->assertSame([], VisitObserveCurriculumRows::defaultRows());
        $this->assertSame(['GrapeSEED', 'LittleSEED'], VisitObserveCurriculumRows::addableTypes([]));
    }

    public function test_adds_grapeseed_and_littleseed_rows_once_each(): void
    {
        $rows = VisitObserveCurriculumRows::add([], 'LittleSEED');
        $rows = VisitObserveCurriculumRows::add($rows, 'GrapeSEED');
        $rows = VisitObserveCurriculumRows::add($rows, 'LittleSEED');

        $this->assertSame(['LittleSEED', 'GrapeSEED'], array_column($rows, 'type'));
        $this->assertSame([], VisitObserveCurriculumRows::addableTypes($rows));
    }

    public function test_can_remove_grapeseed_row(): void
    {
        $rows = VisitObserveCurriculumRows::add([], 'GrapeSEED');
        $rows = VisitObserveCurriculumRows::add($rows, 'LittleSEED');
        $rows = VisitObserveCurriculumRows::remove($rows, 0);

        $this->assertSame(['LittleSEED'], array_column($rows, 'type'));
    }

    public function test_apply_to_payload_syncs_grapeseed_scalars(): void
    {
        $payload = VisitObserveCurriculumRows::applyToPayload([
            'observe_rows' => [
                [
                    'type' => 'GrapeSEED',
                    'unit' => '2',
                    'lesson' => '3',
                    'class' => 'A반',
                    'age' => '5',
                ],
                [
                    'type' => 'LittleSEED',
                    'unit' => '1',
                    'lesson' => '',
                    'class' => '',
                    'age' => '',
                ],
            ],
        ]);

        $this->assertSame(2, $payload['observe_unit']);
        $this->assertSame(3, $payload['observe_lesson']);
        $this->assertSame('A반', $payload['observe_class']);
        $this->assertSame('5', $payload['observe_age']);
        $this->assertSame('LittleSEED', $payload['observe_curriculum_rows'][1]['type']);
        $this->assertSame(1, $payload['observe_curriculum_rows'][1]['unit']);
    }

    public function test_hydrate_falls_back_to_legacy_scalar_columns(): void
    {
        $form = VisitObserveCurriculumRows::hydrateForm([
            'observe_unit' => 8,
            'observe_lesson' => 1,
            'observe_class' => 'B',
            'observe_age' => '6',
        ]);

        $this->assertSame('GrapeSEED', $form['observe_rows'][0]['type']);
        $this->assertSame(8, $form['observe_rows'][0]['unit']);
        $this->assertSame('B', $form['observe_rows'][0]['class']);
    }

    public function test_littleseed_uses_set_and_day_labels(): void
    {
        $labels = VisitObserveCurriculumRows::fieldLabels('LittleSEED');

        $this->assertSame('SET', $labels['unit']);
        $this->assertSame('Day', $labels['lesson']);
        $this->assertSame('Unit', VisitObserveCurriculumRows::fieldLabels('GrapeSEED')['unit']);
        $this->assertSame(
            'LittleSEED SET 4 / Day 1',
            VisitObserveCurriculumRows::formatForDisplay([
                ['type' => 'LittleSEED', 'unit' => 4, 'lesson' => 1, 'class' => '', 'age' => ''],
            ]),
        );
    }
}
