<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Category;
use App\Models\Notification;
use App\Models\ReportModerationReport;
use App\Models\ReportComment;
use App\Models\Room;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\ServiceUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_create_a_report_with_automatic_priority_and_history(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);

        $response = $this->actingAs($resident)->post(route('reports.store'), [
            'category_id' => $category->id,
            'title' => 'Internet kamar terputus',
            'description' => 'Koneksi tidak tersedia sejak pagi.',
            'location_type' => 'room',
            'room_id' => $room->id,
            'visibility' => 'public_building',
            'identity_visibility' => 'visible',
            'assessment_answers' => [
                'affects_many' => 'yes',
                'unusable' => 'yes',
                'safety_risk' => 'no',
                'worsening' => 'no',
                'ongoing' => 'yes',
            ],
            'incident_duration' => 'today',
        ]);
        $report = \App\Models\Report::firstOrFail();
        $response->assertRedirect(route('reports.show', $report));
        $this->assertSame('normal', $report->priority);
        $this->assertSame(11, $report->priority_score);
        $this->assertSame('participants', $report->visibility);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'to_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('report_priority_histories', [
            'report_id' => $report->id,
            'new_priority' => 'normal',
        ]);
    }

    public function test_room_report_uses_reporters_room_and_rejects_another_room(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $otherRoom = Room::create(['building_id' => $building->id, 'room_number' => '302']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($resident)->post(route('reports.store'), [
            'category_id' => $category->id,
            'title' => 'Laporan kamar lain',
            'description' => 'Tidak boleh.',
            'location_type' => 'room',
            'room_id' => $otherRoom->id,
            'visibility' => 'participants',
            'identity_visibility' => 'visible',
            'assessment_answers' => $this->answers(),
            'incident_duration' => 'today',
        ])->assertSessionHasErrors('room_id');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_two_residents_in_one_room_can_each_create_a_report(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $first = User::factory()->create(['role' => 'resident', 'building_id' => $building->id, 'room_id' => $room->id]);
        $second = User::factory()->create(['role' => 'resident', 'building_id' => $building->id, 'room_id' => $room->id]);

        foreach ([$first, $second] as $resident) {
            $this->actingAs($resident)->post(route('reports.store'), [
                'category_id' => $category->id,
                'title' => 'Keluhan kamar',
                'description' => 'Keluhan penghuni.',
                'location_type' => 'room',
                'room_id' => $room->id,
                'visibility' => 'participants',
                'identity_visibility' => 'visible',
                'assessment_answers' => $this->answers(),
                'incident_duration' => 'today',
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('reports', 2);
    }

    public function test_common_area_requires_location_description(): void
    {
        [$building, , $category] = $this->referenceData();
        $resident = User::factory()->create(['role' => 'resident', 'building_id' => $building->id]);

        $this->actingAs($resident)->post(route('reports.store'), [
            'category_id' => $category->id,
            'title' => 'Lampu lorong mati',
            'description' => 'Lampu tidak menyala.',
            'location_type' => 'common_area',
            'visibility' => 'public_building',
            'identity_visibility' => 'visible',
            'assessment_answers' => $this->answers(),
            'incident_duration' => 'today',
        ])->assertSessionHasErrors('location_description');
    }

    public function test_resident_cannot_view_a_private_report_from_another_building(): void
    {
        [$building, , $category] = $this->referenceData();
        $otherBuilding = Building::create(['name' => 'Rusunawa Putra']);
        $owner = User::factory()->create([
            'role' => 'resident',
            'building_id' => $otherBuilding->id,
        ]);
        $viewer = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
        ]);
        $report = \App\Models\Report::create([
            'ticket_number' => 'LAP-20261020-ABC123',
            'reporter_id' => $owner->id,
            'building_id' => $otherBuilding->id,
            'category_id' => $category->id,
            'title' => 'Privat',
            'description' => 'Rahasia',
            'location_type' => 'building',
        ]);

        $this->actingAs($viewer)->get(route('reports.show', $report))->assertForbidden();
    }

    public function test_visible_identity_is_shown_to_same_building_resident_but_room_is_hidden(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $owner = User::factory()->create([
            'name' => 'Pelapor Terlihat',
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $viewer = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $report = Report::create([
            'ticket_number' => 'LAP-IDENTITY-001',
            'reporter_id' => $owner->id,
            'building_id' => $building->id,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'title' => 'Laporan publik',
            'description' => 'Dapat dilihat penghuni gedung.',
            'location_type' => 'common_area',
            'visibility' => 'public_building',
            'identity_visibility' => 'visible',
        ]);

        $this->actingAs($viewer)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Pelapor Terlihat')
            ->assertDontSee('Kamar pelapor: '.$room->room_number);
    }

    public function test_anonymous_identity_is_shown_to_manager_and_assigned_staff(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $owner = User::factory()->create([
            'name' => 'Pelapor Sensitif',
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Pemeliharaan']);
        $report = Report::create([
            'ticket_number' => 'LAP-IDENTITY-002',
            'reporter_id' => $owner->id,
            'building_id' => $building->id,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'title' => 'Laporan anonim',
            'description' => 'Identitas disamarkan untuk penghuni lain.',
            'location_type' => 'common_area',
            'visibility' => 'public_building',
            'identity_visibility' => 'anonymous',
        ]);
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.show', $report))
            ->assertSee('Pelapor Sensitif')
            ->assertSee('Kamar pelapor: '.$room->room_number);
        $this->actingAs($staff)->get(route('reports.show', $report))
            ->assertSee('Pelapor Sensitif')
            ->assertSee('Kamar pelapor: '.$room->room_number);
    }

    public function test_anonymous_identity_is_hidden_from_other_building_resident_viewer(): void
    {
        [$building, , $category] = $this->referenceData();
        $owner = User::factory()->create(['role' => 'resident', 'building_id' => $building->id]);
        $viewer = User::factory()->create(['role' => 'resident', 'building_id' => $building->id]);
        $report = Report::create([
            'ticket_number' => 'LAP-IDENTITY-003',
            'reporter_id' => $owner->id,
            'building_id' => $building->id,
            'category_id' => $category->id,
            'title' => 'Laporan anonim',
            'description' => 'Laporan publik anonim.',
            'location_type' => 'building',
            'visibility' => 'public_building',
            'identity_visibility' => 'anonymous',
        ]);

        $this->actingAs($viewer)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Nama: Anonim')
            ->assertDontSee($owner->name);
    }

    public function test_manager_can_assign_report_to_valid_unit_and_staff(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Pemeliharaan']);
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'submitted');

        $this->actingAs($manager)->post(route('reports.assignments.store', $report), [
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'note' => 'Ditugaskan untuk pemeriksaan.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertDatabaseHas('report_assignments', [
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
        ]);
        $this->assertSame('assigned', $report->fresh()->status);
    }

    public function test_report_detail_shows_active_staff_name_and_unit(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['name' => 'Petugas Detail', 'role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Pemeliharaan']);
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'assigned');
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Nama: Petugas Detail')
            ->assertSee('Unit layanan: Pemeliharaan');
    }

    public function test_report_detail_shows_unassigned_when_no_active_staff_exists(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['reporter_id' => $resident->id]);

        $this->actingAs($resident)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Belum ditugaskan.')
            ->assertDontSee('Unit layanan:');
    }

    public function test_staff_can_update_assigned_report_but_cannot_assign_it(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Pemeliharaan']);
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'assigned');
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($staff)->patch(route('reports.triage.update', $report), [
            'priority' => 'high',
            'reason' => 'Pemeriksaan sedang dilakukan.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertSame('assigned', $report->fresh()->status);
        $this->assertSame('high', $report->fresh()->priority);
        $this->assertDatabaseHas('report_priority_histories', [
            'report_id' => $report->id,
            'old_priority' => 'normal',
            'new_priority' => 'high',
        ]);

        $this->actingAs($staff)->post(route('reports.triage.status', $report), [
            'status' => 'in_progress',
            'note' => 'Pemeriksaan sedang dilakukan.',
        ])->assertRedirect(route('reports.show', $report));
        $this->assertSame('in_progress', $report->fresh()->status);

        $this->actingAs($staff)->post(route('reports.assignments.store', $report), [
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
        ])->assertForbidden();
    }

    public function test_priority_change_requires_reason_and_manager_triage_is_automatic(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $report = $this->makeReport($building, $room, $category, 'submitted');

        $this->actingAs($manager)->patch(route('reports.triage.update', $report), [
            'priority' => 'critical',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($manager)->patch(route('reports.triage.update', $report), [
            'priority' => 'normal',
            'reason' => 'Hasil peninjauan awal.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertSame('triaged', $report->fresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'from_status' => 'submitted',
            'to_status' => 'triaged',
        ]);
    }

    public function test_priority_cannot_be_changed_after_work_is_waiting_for_confirmation(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $report = $this->makeReport($building, $room, $category, 'waiting_confirmation');

        $this->actingAs($manager)->patch(route('reports.triage.update', $report), [
            'priority' => 'critical',
            'reason' => 'Tidak boleh diubah setelah pekerjaan selesai.',
        ])->assertForbidden();

        $this->assertSame('normal', $report->fresh()->priority);
        $this->assertDatabaseMissing('report_priority_histories', [
            'report_id' => $report->id,
            'new_priority' => 'critical',
        ]);
    }

    public function test_report_detail_shows_available_estimates_to_the_reporter(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $report->update([
            'estimated_response_at' => '2026-10-28 09:00:00',
            'estimated_resolution_at' => '2026-10-29 17:30:00',
            'estimate_note' => 'Petugas akan menghubungi penghuni sebelum pemeriksaan.',
        ]);

        $this->actingAs($report->reporter)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Estimasi respons: 28/10/2026 09:00')
            ->assertSee('Estimasi selesai: 29/10/2026 17:30')
            ->assertSee('Petugas akan menghubungi penghuni sebelum pemeriksaan.');
    }

    public function test_staff_cannot_skip_status_transition(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Keamanan']);
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'assigned');
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('reports.triage.status', $report), [
            'status' => 'resolved',
            'note' => 'Tidak boleh melewati proses.',
        ])->assertStatus(422);
        $this->assertSame('assigned', $report->fresh()->status);
    }

    public function test_staff_can_only_move_from_assigned_to_in_progress_and_then_waiting_confirmation(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::create(['name' => 'Keamanan']);
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'assigned');
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('reports.triage.status', $report), [
            'status' => 'waiting_reporter',
            'note' => 'Status lama tidak digunakan.',
        ])->assertSessionHasErrors('status');

        $this->actingAs($staff)->post(route('reports.triage.status', $report), [
            'status' => 'in_progress',
            'note' => 'Penanganan dimulai.',
        ])->assertRedirect(route('reports.show', $report));

        $this->actingAs($staff)->post(route('reports.triage.status', $report), [
            'status' => 'waiting_confirmation',
            'note' => 'Kendala sudah ditangani.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertSame('waiting_confirmation', $report->fresh()->status);
        $this->assertDatabaseMissing('report_status_histories', [
            'report_id' => $report->id,
            'to_status' => 'waiting_reporter',
        ]);
    }

    public function test_reporter_can_close_waiting_report_and_retention_is_two_years(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'waiting_confirmation');

        $this->actingAs($report->reporter)->post(route('reports.close', $report))
            ->assertRedirect(route('reports.show', $report));

        $closed = $report->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertEquals(
            $closed->closed_at->copy()->addYears(2)->timestamp,
            $closed->retained_until->timestamp,
        );
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'from_status' => 'waiting_confirmation',
            'to_status' => 'closed',
        ]);
    }

    public function test_manager_can_reopen_closed_report_and_clears_retention(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'closed');
        $report->update([
            'closed_at' => now()->subDay(),
            'retained_until' => now()->addYear(),
        ]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('reports.reopen', $report), [
            'note' => 'Penghuni melaporkan kendala masih ada.',
        ])->assertRedirect(route('reports.show', $report));

        $reopened = $report->fresh();
        $this->assertSame('reopened', $reopened->status);
        $this->assertNull($reopened->closed_at);
        $this->assertNull($reopened->retained_until);

    }

    public function test_reporter_can_reopen_a_closed_report(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'closed');

        $this->actingAs($report->reporter)->post(route('reports.reopen', $report), [
            'note' => 'Kendala masih terjadi.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertSame('reopened', $report->fresh()->status);
    }

    public function test_manager_can_filter_the_work_queue_and_staff_is_limited_to_active_assignments(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $unit = ServiceUnit::whereKey($category->default_service_unit_id)->firstOrFail();
        $unit->buildings()->attach($building);
        $staff->serviceUnits()->attach($unit);
        $staff->buildings()->attach($building);
        $report = $this->makeReport($building, $room, $category, 'assigned');
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $unit->id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.index', [
            'priority' => 'normal',
            'status' => 'assigned',
        ]))->assertOk()->assertSee($report->ticket_number);
        $this->actingAs($staff)->get(route('reports.index'))
            ->assertOk()
            ->assertSee($report->ticket_number);
        $this->actingAs($staff)->get(route('reports.queue'))
            ->assertOk()
            ->assertSee($report->ticket_number);
        $this->actingAs($report->reporter)->get(route('reports.queue'))->assertForbidden();
    }

    public function test_estimate_changes_require_a_reason_and_are_audited(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $report = $this->makeReport($building, $room, $category, 'triaged');
        $payload = [
            'priority' => 'normal',
            'estimated_response_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'estimated_resolution_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'estimate_note' => 'Pemeriksaan dijadwalkan.',
        ];

        $this->actingAs($manager)->patch(route('reports.triage.update', $report), $payload)
            ->assertSessionHasErrors('estimate_reason');
        $this->actingAs($manager)->patch(route('reports.triage.update', $report), $payload + [
            'estimate_reason' => 'Penjadwalan unit layanan.',
        ])->assertRedirect();

        $this->assertDatabaseHas('report_estimate_histories', [
            'report_id' => $report->id,
            'reason' => 'Penjadwalan unit layanan.',
        ]);
    }

    public function test_overdue_sla_notifications_are_idempotent(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $manager = User::factory()->create(['role' => 'manager']);
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['estimated_response_at' => now()->subHour()]);

        $this->artisan('reports:notify-overdue')->assertExitCode(0);
        $this->assertDatabaseCount('notifications', 2);
        $this->artisan('reports:notify-overdue')->assertExitCode(0);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_comment_can_include_a_private_attachment_and_notifies_other_participants(): void
    {
        Storage::fake('local');
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($report->reporter)->post(route('reports.comments.store', $report), [
            'body' => 'Saya menambahkan bukti terbaru.',
            'attachments' => [UploadedFile::fake()->create('bukti.png', 100, 'image/png')],
        ])->assertRedirect(route('reports.show', $report));

        $attachment = $report->comments()->firstOrFail()->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'report.comment_added',
        ]);
    }

    public function test_resident_in_the_same_building_can_join_a_public_report_conversation(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($resident)->post(route('community.acknowledge', $report))
            ->assertRedirect();
        $this->actingAs($resident)->post(route('reports.comments.store', $report), [
            'body' => 'Saya juga mengalami kendala yang sama.',
        ])->assertRedirect(route('reports.show', $report));

        $this->assertDatabaseHas('report_comments', [
            'report_id' => $report->id,
            'user_id' => $resident->id,
            'body' => 'Saya juga mengalami kendala yang sama.',
        ]);
    }

    public function test_public_report_conversation_can_be_reported_hidden_and_disabled(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $manager = User::factory()->create(['role' => 'manager']);
        $comment = ReportComment::create([
            'report_id' => $report->id,
            'user_id' => $report->reporter_id,
            'body' => 'Komentar yang perlu ditinjau.',
        ]);

        $this->actingAs($resident)->post(route('comments.report', $comment), [
            'reason' => 'personal_data',
            'note' => 'Komentar membocorkan informasi pribadi.',
        ])->assertRedirect();
        $this->assertDatabaseHas('report_comment_moderation_reports', [
            'comment_id' => $comment->id,
            'reported_by' => $resident->id,
            'reason' => 'personal_data',
            'note' => 'Komentar membocorkan informasi pribadi.',
            'status' => 'open',
        ]);

        $this->actingAs($manager)->post(route('comments.hide', $comment))
            ->assertRedirect();
        $this->assertNotNull($comment->fresh()->deleted_at);

        $this->actingAs($manager)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Tampilkan kembali komentar');
        $this->actingAs($manager)->post(route('comments.restore', $comment->id))
            ->assertRedirect();
        $this->assertNull($comment->fresh()->deleted_at);
        $this->assertDatabaseHas('report_comment_moderation_reports', [
            'comment_id' => $comment->id,
            'status' => 'resolved',
            'reviewed_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('reports.community-chat.disable', $report))
            ->assertRedirect();
        $this->assertNotNull($report->fresh()->community_chat_disabled_at);

        $this->actingAs($resident)->post(route('reports.comments.store', $report), [
            'body' => 'Tidak boleh masuk setelah dinonaktifkan.',
        ])->assertForbidden();
    }

    public function test_reported_comment_notifications_only_go_to_managers_with_comment_details(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $report->update(['visibility' => 'public_building']);
        $staff = User::factory()->create(['role' => 'staff']);
        $report->assignments()->create([
            'service_unit_id' => ServiceUnit::firstOrFail()->id,
            'staff_id' => $staff->id,
            'assigned_at' => now(),
        ]);
        $manager = User::factory()->create(['role' => 'manager']);
        $comment = ReportComment::create([
            'report_id' => $report->id,
            'user_id' => $report->reporter_id,
            'body' => 'Komentar yang dilaporkan petugas.',
        ]);

        $this->actingAs($staff)->post(route('comments.report', $comment), [
            'reason' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'report.comment_reported',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $staff->id,
            'type' => 'report.comment_reported',
        ]);
        $notification = Notification::where('user_id', $manager->id)
            ->where('type', 'report.comment_reported')
            ->firstOrFail();
        $this->assertSame($comment->id, $notification->data['comment_id']);
        $this->assertStringContainsString('Komentar #'.$comment->id, $notification->data['message']);
    }

    public function test_disabling_public_conversation_does_not_block_reporter_or_assigned_staff(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'in_progress');
        $report->update(['visibility' => 'public_building']);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $report->assignments()->create([
            'service_unit_id' => ServiceUnit::firstOrFail()->id,
            'staff_id' => $staff->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)->post(route('reports.community-chat.disable', $report))
            ->assertRedirect();
        $this->assertTrue($report->fresh()->community_chat_disabled_at !== null);

        foreach ([$report->reporter, $staff] as $participant) {
            $this->actingAs($participant)->post(route('reports.comments.store', $report), [
                'body' => 'Percakapan internal tetap berjalan.',
            ])->assertRedirect();
        }
    }

    public function test_other_resident_cannot_report_comments_after_public_conversation_is_disabled(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'in_progress');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $this->actingAs($resident)->post(route('community.acknowledge', $report))
            ->assertRedirect();
        $comment = ReportComment::create([
            'report_id' => $report->id,
            'user_id' => $report->reporter_id,
            'body' => 'Komentar pada percakapan publik.',
        ]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('reports.community-chat.disable', $report))
            ->assertRedirect();
        $this->actingAs($resident)->post(route('comments.report', $comment), [
            'reason' => 'spam',
        ])->assertForbidden();
        $this->assertDatabaseMissing('report_comment_moderation_reports', [
            'comment_id' => $comment->id,
            'reported_by' => $resident->id,
        ]);
    }

    public function test_manager_cannot_disable_conversation_after_report_is_closed(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'closed');
        $report->update(['visibility' => 'public_building']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('reports.community-chat.disable', $report))
            ->assertStatus(422);
        $this->assertNull($report->fresh()->community_chat_disabled_at);
    }

    public function test_resident_from_another_building_cannot_join_public_report_conversation(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $otherBuilding = Building::create(['name' => 'Rusunawa Putra']);
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $otherBuilding->id,
        ]);

        $this->actingAs($resident)->post(route('reports.comments.store', $report), [
            'body' => 'Komentar dari gedung lain.',
        ])->assertForbidden();
    }

    public function test_user_can_read_only_their_own_notifications(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $notification = Notification::create([
            'user_id' => $report->reporter_id,
            'type' => 'report.status_changed',
            'data' => ['report_id' => $report->id, 'message' => 'Status berubah.'],
        ]);
        $otherUser = User::factory()->create(['role' => 'resident']);

        $this->actingAs($report->reporter)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Status berubah.')
            ->assertSee('Ke laporan');
        $this->actingAs($otherUser)->patch(route('notifications.read', $notification))
            ->assertForbidden();
        $this->actingAs($report->reporter)->patch(route('notifications.read', $notification))
            ->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_can_open_its_report_and_mark_it_read(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $notification = Notification::create([
            'user_id' => $report->reporter_id,
            'type' => 'report.comment_added',
            'data' => ['report_id' => $report->id, 'message' => 'Komentar baru.'],
        ]);

        $this->actingAs($report->reporter)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('reports.show', $report));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_resident_can_use_the_building_community_feed_and_acknowledge_once(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building', 'location_type' => 'common_area']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($resident)->get(route('community.index'))
            ->assertOk()
            ->assertSee($report->title);
        $this->actingAs($report->reporter)->get(route('reports.index'))
            ->assertOk()
            ->assertSee($report->title);
        $this->actingAs($resident)->post(route('community.acknowledge', $report))
            ->assertRedirect();
        $this->actingAs($resident)->post(route('community.acknowledge', $report))
            ->assertRedirect();

        $this->assertDatabaseCount('report_acknowledgements', 1);
    }

    public function test_resident_from_another_building_cannot_access_the_community_report(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $otherBuilding = Building::create(['name' => 'Gedung Lain']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $otherBuilding->id,
        ]);

        $this->actingAs($resident)->get(route('community.index'))->assertOk()->assertDontSee($report->title);
        $this->actingAs($resident)->post(route('community.acknowledge', $report))->assertForbidden();
    }

    public function test_resident_can_report_public_content_and_manager_can_moderate_it(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($resident)->post(route('community.report', $report), [
            'reason' => 'inappropriate',
            'note' => 'Perlu ditinjau.',
        ])->assertRedirect();
        $this->assertDatabaseHas('report_moderation_reports', [
            'report_id' => $report->id,
            'reported_by' => $resident->id,
            'status' => 'open',
        ]);

        $this->actingAs($manager)->post(route('community.hide', $report))
            ->assertRedirect();
        $this->assertNotNull($report->fresh()->community_hidden_at);
        $this->assertSame('resolved', ReportModerationReport::firstOrFail()->status);
    }

    public function test_staff_cannot_access_community(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $staff = User::factory()->create(['role' => 'staff']);
        $staff->buildings()->attach($building);

        $this->actingAs($staff)->get(route('community.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('community.hide', $report))->assertForbidden();
    }

    public function test_only_residents_can_acknowledge_and_duplicate_flags_do_not_require_a_report_id(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('community.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('community.acknowledge', $report))->assertForbidden();
        $this->actingAs($report->reporter)->post(route('community.acknowledge', $report))->assertStatus(422);
        $this->actingAs($resident)->post(route('community.duplicate', $report))->assertRedirect();
        $this->assertDatabaseHas('report_moderation_reports', [
            'report_id' => $report->id,
            'reason' => 'duplicate',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $report->reporter_id,
            'type' => 'report.community_reported',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'report.community_reported',
        ]);
        $this->actingAs($manager)->get(route('community.moderation'))
            ->assertOk()
            ->assertSee($report->title);
    }

    public function test_duplicate_flag_reopens_resolved_flag_for_manager_review(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $resident = User::factory()->create([
            'role' => 'resident',
            'building_id' => $building->id,
            'room_id' => $room->id,
        ]);
        $manager = User::factory()->create(['role' => 'manager']);
        ReportModerationReport::create([
            'report_id' => $report->id,
            'reported_by' => $resident->id,
            'reason' => 'duplicate',
            'status' => 'resolved',
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($resident)->post(route('community.duplicate', $report))
            ->assertRedirect();

        $this->assertDatabaseHas('report_moderation_reports', [
            'report_id' => $report->id,
            'reported_by' => $resident->id,
            'reason' => 'duplicate',
            'status' => 'open',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function test_report_owner_cannot_report_or_mark_own_report_as_duplicate(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $report->update(['visibility' => 'public_building']);
        $owner = $report->reporter;

        $this->actingAs($owner)->get(route('community.index'))
            ->assertOk()
            ->assertDontSee('Laporkan konten')
            ->assertDontSee('Tandai duplikat');
        $this->actingAs($owner)->post(route('community.report', $report), [
            'reason' => 'other',
        ])->assertStatus(422);
        $this->actingAs($owner)->post(route('community.duplicate', $report))
            ->assertStatus(422);
    }

    public function test_staff_and_manager_can_flag_accessible_reports_from_reports_page(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'assigned');
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        ReportAssignment::create([
            'report_id' => $report->id,
            'service_unit_id' => $category->default_service_unit_id,
            'staff_id' => $staff->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporkan untuk moderasi')
            ->assertSee('Tandai duplikat');
        $this->actingAs($staff)->post(route('community.duplicate', $report))
            ->assertRedirect();
        $this->assertDatabaseHas('report_moderation_reports', [
            'report_id' => $report->id,
            'reported_by' => $staff->id,
            'reason' => 'duplicate',
        ]);
    }

    public function test_manager_community_report_notification_opens_moderation(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $manager = User::factory()->create(['role' => 'manager']);
        $notification = Notification::create([
            'user_id' => $manager->id,
            'type' => 'report.community_reported',
            'data' => ['report_id' => $report->id, 'message' => 'Laporan dilaporkan.'],
        ]);

        $this->actingAs($manager)->get(route('notifications.index'))
            ->assertSee('Ke Moderasi')
            ->assertDontSee('Ke laporan');
        $this->actingAs($manager)->get(route('notifications.open', $notification))
            ->assertRedirect(route('community.moderation'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_resident_reports_page_excludes_public_reports_and_manager_can_review_duplicate(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $public = $this->makeReport($building, $room, $category, 'submitted');
        $public->update(['visibility' => 'public_building', 'title' => 'Laporan publik']);
        $private = $this->makeReport($building, $room, $category, 'submitted');
        $private->update(['title' => 'Laporan pribadi']);
        $canonical = $this->makeReport($building, $room, $category, 'submitted');
        $canonical->update(['visibility' => 'public_building', 'title' => 'Laporan utama']);
        $resident = $private->reporter;
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($resident)->get(route('reports.index'))
            ->assertOk()
            ->assertSee($private->title)
            ->assertDontSee($public->title);
        ReportModerationReport::create([
            'report_id' => $public->id,
            'reported_by' => $resident->id,
            'reason' => 'duplicate',
            'status' => 'open',
        ]);
        $this->actingAs($manager)->post(route('community.review', $public), [
            'action' => 'confirm_duplicate',
            'duplicate_of_report_id' => $canonical->id,
        ])->assertRedirect();
        $this->assertSame($canonical->id, $public->fresh()->duplicate_of_report_id);
    }

    public function test_manager_can_restore_a_hidden_report_without_changing_visibility(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'waiting_confirmation');
        $report->update(['visibility' => 'public_building', 'community_hidden_at' => now()]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('community.restore', $report))->assertRedirect();
        $restored = $report->fresh();
        $this->assertNull($restored->community_hidden_at);
        $this->assertSame('public_building', $restored->visibility);
    }

    public function test_manager_can_cancel_duplicate_status_and_restore_report_to_community(): void
    {
        [$building, $room, $category] = $this->referenceData();
        $report = $this->makeReport($building, $room, $category, 'submitted');
        $canonical = $this->makeReport($building, $room, $category, 'submitted');
        $report->update([
            'visibility' => 'public_building',
            'duplicate_of_report_id' => $canonical->id,
            'community_hidden_at' => now(),
        ]);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('community.cancel-duplicate', $report))
            ->assertRedirect();

        $restored = $report->fresh();
        $this->assertNull($restored->duplicate_of_report_id);
        $this->assertNull($restored->community_hidden_at);
        $this->actingAs(User::findOrFail($report->reporter_id))
            ->get(route('community.index'))
            ->assertSee($report->title);
    }

    public function test_only_manager_can_access_community_moderation(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($resident)->get(route('community.moderation'))->assertForbidden();
        $this->actingAs($staff)->get(route('community.moderation'))->assertForbidden();
        $this->actingAs($manager)->get(route('community.moderation'))->assertOk();
    }

    private function referenceData(): array
    {
        $building = Building::create(['name' => 'Rusun INN']);
        $room = Room::create(['building_id' => $building->id, 'room_number' => '301']);
        $unit = ServiceUnit::create(['name' => 'Jaringan']);
        $category = Category::create([
            'name' => 'Internet',
            'default_service_unit_id' => $unit->id,
        ]);

        return [$building, $room, $category];
    }

    private function answers(): array
    {
        return [
            'affects_many' => 'no',
            'unusable' => 'yes',
            'safety_risk' => 'no',
            'worsening' => 'no',
            'ongoing' => 'yes',
        ];
    }

    private function makeReport(Building $building, Room $room, Category $category, string $status): Report
    {
        return Report::create([
            'ticket_number' => 'LAP-TEST-'.uniqid(),
            'reporter_id' => User::factory()->create([
                'role' => 'resident',
                'building_id' => $building->id,
                'room_id' => $room->id,
            ])->id,
            'building_id' => $building->id,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'title' => 'Laporan uji triase',
            'description' => 'Deskripsi laporan uji.',
            'location_type' => 'room',
            'visibility' => 'participants',
            'identity_visibility' => 'visible',
            'priority' => 'normal',
            'priority_score' => 5,
            'status' => $status,
        ]);
    }
}
