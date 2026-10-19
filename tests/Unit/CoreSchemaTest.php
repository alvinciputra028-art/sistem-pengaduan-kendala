<?php

namespace Tests\Unit;

use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\Room;
use App\Models\ServiceUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_tables_have_the_expected_shape(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'role',
            'building_id',
            'room_id',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('reports', [
            'reporter_id',
            'building_id',
            'room_id',
            'category_id',
            'status',
            'priority',
        ]));
        $this->assertFalse(Schema::hasColumn('reports', 'assigned_staff_id'));
        $this->assertFalse(Schema::hasColumn('reports', 'assigned_service_unit_id'));
    }

    public function test_report_relations_preserve_location_and_assignment_history(): void
    {
        $building = Building::create(['name' => 'Rusun INN']);
        $room = Room::create([
            'building_id' => $building->id,
            'room_number' => '301',
        ]);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Pemeliharaan']);
        $category = Category::create([
            'name' => 'Bangunan',
            'default_service_unit_id' => $unit->id,
        ]);
        $report = Report::create([
            'ticket_number' => 'INN-20261019-0001',
            'reporter_id' => $resident->id,
            'building_id' => $building->id,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'title' => 'Ubin retak',
            'description' => 'Ubin kamar retak.',
            'location_type' => 'room',
        ]);
        $assignment = ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($report->building->is($building));
        $this->assertTrue($report->room->is($room));
        $this->assertTrue($report->reporter->is($resident));
        $this->assertTrue($report->assignments->first()->is($assignment));
        $this->assertTrue($assignment->staff->is($staff));
    }

    public function test_service_units_can_cover_multiple_buildings(): void
    {
        $firstBuilding = Building::create(['name' => 'Rusun INN']);
        $secondBuilding = Building::create(['name' => 'Rusunawa Putra']);
        $unit = ServiceUnit::create(['name' => 'Keamanan']);

        $unit->buildings()->attach([
            $firstBuilding->id,
            $secondBuilding->id,
        ]);

        $this->assertCount(2, $unit->fresh()->buildings);
    }
}
