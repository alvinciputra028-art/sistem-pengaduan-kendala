<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Category;
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
            ['Pemeliharaan', 'Pemeliharaan', 'private'],
            ['Kebersihan', 'Kebersihan', 'public_building'],
            ['Keamanan', 'Keamanan', 'manager_only'],
            ['Administrasi', 'Administrasi', 'private'],
        ] as [$name, $unitName, $visibility]) {
            Category::updateOrCreate(
                ['name' => $name],
                [
                    'default_service_unit_id' => $units[$unitName]->id,
                    'default_visibility' => $visibility,
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

        User::updateOrCreate(
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
    }
}
