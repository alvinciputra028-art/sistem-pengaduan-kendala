<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\Room;
use App\Models\ServiceUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $inn = Building::updateOrCreate(['name' => 'Rusun INN'], ['is_active' => true]);
        $putra = Building::updateOrCreate(['name' => 'Rusunawa Putra'], ['is_active' => true]);
        $putri = Building::updateOrCreate(['name' => 'Rusunawa Putri'], ['is_active' => true]);

        foreach ([
            [$inn, '301'],
            [$inn, '302'],
            [$putra, '201'],
            [$putri, '101'],
        ] as [$building, $roomNumber]) {
            Room::updateOrCreate(
                ['building_id' => $building->id, 'room_number' => $roomNumber],
                ['is_active' => true],
            );
        }

        $units = collect([
            'Jaringan',
            'Pemeliharaan',
            'Kebersihan',
            'Keamanan',
            'Administrasi',
        ])->mapWithKeys(function (string $name): array {
            return [$name => ServiceUnit::updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            )];
        });

        $buildingIds = [$inn->id, $putra->id, $putri->id];
        foreach ($units as $unit) {
            $unit->buildings()->sync($buildingIds);
        }

        foreach ([
            ['Jaringan dan Internet', 'Jaringan', 'public_building'],
            ['Pemeliharaan', 'Pemeliharaan', 'participants'],
            ['Kebersihan', 'Kebersihan', 'public_building'],
            ['Keamanan', 'Keamanan', 'participants'],
            ['Administrasi', 'Administrasi', 'participants'],
        ] as [$name, $unitName, $visibility]) {
            Category::updateOrCreate(
                ['name' => $name],
                [
                    'default_service_unit_id' => $units[$unitName]->id,
                    'default_visibility' => $visibility,
                    'is_sensitive' => in_array($name, ['Keamanan', 'Administrasi'], true),
                    'is_active' => true,
                ],
            );
        }

        $innRoom = Room::where('building_id', $inn->id)
            ->where('room_number', '301')
            ->firstOrFail();
        $putraRoom = Room::where('building_id', $putra->id)
            ->where('room_number', '201')
            ->firstOrFail();
        $putriRoom = Room::where('building_id', $putri->id)
            ->where('room_number', '101')
            ->firstOrFail();

        $resident = User::updateOrCreate(
            ['email' => 'resident@example.test'],
            [
                'name' => 'Penghuni Demo',
                'password' => Hash::make('password'),
                'role' => 'resident',
                'building_id' => $inn->id,
                'room_id' => $innRoom->id,
                'is_active' => true,
            ],
        );

        $roommate = User::updateOrCreate(
            ['email' => 'resident-roommate@example.test'],
            [
                'name' => 'Teman Sekamar Demo',
                'password' => Hash::make('password'),
                'role' => 'resident',
                'building_id' => $inn->id,
                'room_id' => $innRoom->id,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'resident-putra@example.test'],
            [
                'name' => 'Penghuni Putra Demo',
                'password' => Hash::make('password'),
                'role' => 'resident',
                'building_id' => $putra->id,
                'room_id' => $putraRoom->id,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'resident-putri@example.test'],
            [
                'name' => 'Penghuni Putri Demo',
                'password' => Hash::make('password'),
                'role' => 'resident',
                'building_id' => $putri->id,
                'room_id' => $putriRoom->id,
                'is_active' => true,
            ],
        );

        $staff = User::updateOrCreate(
            ['email' => 'staff@example.test'],
            [
                'name' => 'Petugas Demo',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'building_id' => null,
                'room_id' => null,
                'is_active' => true,
            ],
        );
        $staff->serviceUnits()->sync([$units['Jaringan']->id, $units['Pemeliharaan']->id]);
        $staff->buildings()->sync([$inn->id, $putra->id]);

        $maintenanceStaff = User::updateOrCreate(
            ['email' => 'staff-maintenance@example.test'],
            [
                'name' => 'Petugas Pemeliharaan Demo',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'building_id' => null,
                'room_id' => null,
                'is_active' => true,
            ],
        );
        $maintenanceStaff->serviceUnits()->sync([$units['Pemeliharaan']->id]);
        $maintenanceStaff->buildings()->sync($buildingIds);

        $securityStaff = User::updateOrCreate(
            ['email' => 'staff-security@example.test'],
            [
                'name' => 'Petugas Keamanan Demo',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'building_id' => null,
                'room_id' => null,
                'is_active' => true,
            ],
        );
        $securityStaff->serviceUnits()->sync([$units['Keamanan']->id]);
        $securityStaff->buildings()->sync($buildingIds);

        User::updateOrCreate(
            ['email' => 'manager@example.test'],
            [
                'name' => 'Manajer Demo',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'building_id' => null,
                'room_id' => null,
                'is_active' => true,
            ],
        );

        $sampleReport = Report::updateOrCreate(
            ['ticket_number' => 'LAP-SEED-0001'],
            [
                'reporter_id' => $resident->id,
                'building_id' => $inn->id,
                'room_id' => $innRoom->id,
                'category_id' => Category::where('name', 'Jaringan dan Internet')->firstOrFail()->id,
                'title' => 'Internet area Rusun INN tidak stabil',
                'description' => 'Laporan contoh untuk menguji akses petugas dan manajer.',
                'location_type' => 'common_area',
                'location_description' => 'Lantai 3 Rusun INN',
                'visibility' => 'public_building',
                'identity_visibility' => 'visible',
                'impact_score' => 2,
                'urgency_score' => 2,
                'safety_risk_score' => 0,
                'assessment_answers' => [
                    'affects_many' => 'yes',
                    'unusable' => 'no',
                    'safety_risk' => 'no',
                    'worsening' => 'no',
                    'ongoing' => 'yes',
                ],
                'incident_duration' => '1_to_3_days',
                'priority' => 'normal',
                'priority_score' => 6,
                'status' => 'assigned',
            ],
        );

        $assignment = ReportAssignment::updateOrCreate(
            ['report_id' => $sampleReport->id, 'staff_id' => $staff->id, 'unassigned_at' => null],
            [
                'service_unit_id' => $units['Jaringan']->id,
                'assigned_by' => User::where('email', 'manager@example.test')->firstOrFail()->id,
                'assigned_at' => now(),
                'note' => 'Assignment contoh untuk pengujian dashboard petugas.',
            ],
        );

        $sampleReport->statusHistories()->firstOrCreate(
            ['to_status' => 'submitted'],
            ['changed_by' => $resident->id, 'note' => 'Laporan contoh dibuat oleh seeder.'],
        );
        $sampleReport->statusHistories()->firstOrCreate(
            ['to_status' => 'assigned'],
            ['from_status' => 'submitted', 'changed_by' => $assignment->assigned_by, 'note' => 'Laporan contoh ditugaskan.'],
        );

        $triageReport = Report::updateOrCreate(
            ['ticket_number' => 'LAP-SEED-0002'],
            [
                'reporter_id' => $roommate->id,
                'building_id' => $inn->id,
                'room_id' => $innRoom->id,
                'category_id' => Category::where('name', 'Pemeliharaan')->firstOrFail()->id,
                'title' => 'Keran kamar bocor',
                'description' => 'Laporan contoh yang menunggu triase manajer.',
                'location_type' => 'room',
                'visibility' => 'participants',
                'identity_visibility' => 'visible',
                'impact_score' => 1,
                'urgency_score' => 2,
                'safety_risk_score' => 0,
                'assessment_answers' => [
                    'affects_many' => 'no',
                    'unusable' => 'no',
                    'safety_risk' => 'no',
                    'worsening' => 'yes',
                    'ongoing' => 'yes',
                ],
                'incident_duration' => 'today',
                'priority' => 'normal',
                'priority_score' => 5,
                'status' => 'submitted',
            ],
        );
        $triageReport->statusHistories()->firstOrCreate(
            ['to_status' => 'submitted'],
            ['changed_by' => $roommate->id, 'note' => 'Laporan triase contoh dibuat oleh seeder.'],
        );
    }
}
