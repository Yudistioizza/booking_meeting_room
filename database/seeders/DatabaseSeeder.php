<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\RecurringBooking;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomAvailabilityRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo tenant: a mid-size company with 12 rooms over three floors,
     * 36 people in six departments and about six weeks of meetings,
     * including recurring stand-ups, pending approvals and cancellations.
     * Every demo account uses the password "password".
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');
        $faker->seed(2026);

        $roles = collect(['Admin' => 'Kelola ruang, pengguna, laporan', 'Manager' => 'Menyetujui booking & melihat laporan', 'Employee' => 'Memesan ruang rapat'])
            ->map(fn ($desc, $name) => Role::create(['name' => $name, 'description' => $desc]));

        $domain = 'nusantara.test';
        $depts = ['Engineering', 'Marketing', 'Finance', 'Human Resources', 'Operations', 'Sales'];

        $admin = User::create(['name' => 'Rina Kusuma', 'email' => "rina.kusuma@$domain", 'department' => 'General Affairs', 'password' => bcrypt('password'), 'role_id' => $roles['Admin']->id, 'email_verified_at' => now()]);
        $managers = collect([['Dimas Pratama', 'Engineering'], ['Sari Wulandari', 'Marketing'], ['Hendra Gunawan', 'Finance']])->map(fn ($m) => User::create([
            'name' => $m[0], 'email' => Str::slug($m[0], '.') . "@$domain", 'department' => $m[1], 'password' => bcrypt('password'), 'role_id' => $roles['Manager']->id, 'email_verified_at' => now(),
        ]));
        $staff = collect(range(1, 32))->map(function () use ($faker, $domain, $depts, $roles) {
            $name = $faker->firstName() . ' ' . $faker->lastName();

            return User::create([
                'name' => $name, 'email' => Str::slug($name, '.') . $faker->unique()->numberBetween(1, 99) . "@$domain",
                'department' => $faker->randomElement($depts), 'password' => bcrypt('password'), 'role_id' => $roles['Employee']->id, 'email_verified_at' => now(),
            ]);
        });
        $everyone = $managers->concat($staff)->push($admin);

        // name, location, capacity, equipment, approval, max minutes, buffer, availability
        $rooms = collect([
            ['Lobby Lounge', 'Lantai 1 · Dekat resepsionis', 6, 'TV 43", Sofa, Wi-Fi tamu', false, 120, 0, null],
            ['Meeting A', 'Lantai 1 · Sayap barat', 4, 'TV 43", Whiteboard', false, null, 0, null],
            ['Meeting B', 'Lantai 1 · Sayap barat', 4, 'TV 43", Whiteboard', false, null, 0, null],
            ['Phone Booth', 'Lantai 1 · Koridor', 1, 'Meja berdiri, Peredam suara', false, 60, 0, null],
            ['Borobudur', 'Lantai 2 · Sayap timur', 12, 'Proyektor, Video conference, Whiteboard', false, null, 10, null],
            ['Prambanan', 'Lantai 2 · Sayap timur', 12, 'Proyektor, Video conference, Whiteboard', false, null, 10, null],
            ['Bromo', 'Lantai 2 · Sayap barat', 8, 'TV 55", Video conference', false, null, 0, null],
            ['Rinjani', 'Lantai 2 · Sayap barat', 8, 'TV 55", Whiteboard', false, null, 0, null],
            ['Merapi', 'Lantai 3 · Ruang eksekutif', 20, 'Proyektor, Sound system, Video conference', true, 240, 15, ['Mon,Tue,Wed,Thu,Fri', '08:00-18:00']],
            ['Semeru Boardroom', 'Lantai 3 · Ruang eksekutif', 14, 'Layar 85", Video conference, Catering pantry', true, 240, 15, ['Mon,Tue,Wed,Thu,Fri', '08:00-18:00']],
            ['Krakatau Auditorium', 'Lantai 3 · Aula', 80, 'Panggung, Sound system, 2 proyektor, Live streaming', true, 480, 30, ['Mon,Tue,Wed,Thu,Fri,Sat', '08:00-17:00']],
            ['Training Room', 'Lantai 3 · Sayap timur', 30, 'Proyektor, Laptop 15 unit, Whiteboard', false, 480, 15, ['Mon,Tue,Wed,Thu,Fri', '08:00-12:00,13:00-17:00']],
        ])->map(function ($r) use ($admin, $domain) {
            $room = Room::create([
                'name' => $r[0], 'location' => $r[1], 'capacity' => $r[2], 'equipment' => $r[3], 'requires_approval' => $r[4],
                'max_duration_minutes' => $r[5], 'buffer_minutes' => $r[6], 'created_by' => $admin->email,
                'calendar_email' => 'room.' . Str::slug($r[0]) . "@$domain",
            ]);
            if ($r[7]) {
                RoomAvailabilityRule::create(['room_id' => $room->id, 'available_days' => $r[7][0], 'available_hours' => $r[7][1]]);
            }

            return $room;
        });

        $titles = ['Weekly Sync', 'Sprint Planning', 'Retrospective', 'Client Presentation', '1:1 Review', 'Interview Kandidat', 'Review Anggaran', 'Brainstorming Kampanye',
            'Demo Produk', 'Onboarding Karyawan Baru', 'Rapat Koordinasi', 'Workshop Tim', 'Negosiasi Vendor', 'Town Hall Departemen', 'Pelatihan K3'];
        $host = fn () => $everyone->random();
        $uid = fn () => (string) Str::uuid() . '@' . $domain;
        $today = Carbon::today();

        // Regular meetings from four weeks ago to two weeks ahead, workdays only
        $freeRooms = $rooms->reject(fn ($r) => $r->requires_approval || $r->capacity < 2);
        for ($d = -28; $d <= 14; $d++) {
            $day = $today->copy()->addDays($d);
            if ($day->isWeekend()) {
                continue;
            }
            foreach ($freeRooms as $room) {
                $busy = $faker->numberBetween(2, 5);
                $hour = 8;
                for ($i = 0; $i < $busy && $hour < 17; $i++) {
                    $hour += $faker->numberBetween(0, 2);
                    $len = $faker->randomElement([1, 1, 2, 1.5, 0.5]);
                    $s = $day->copy()->setTime($hour, $faker->randomElement([0, 30]));
                    $e = $s->copy()->addMinutes($len * 60);
                    if ($e->hour >= 18 || ($e->hour === 17 && $e->minute > 30)) {
                        break;
                    }
                    $creator = $host();
                    Booking::create([
                        'room_id' => $room->id, 'created_by' => $creator->id, 'title' => $faker->randomElement($titles) . ' ' . $creator->department,
                        'start_time' => $s, 'end_time' => $e, 'status' => 'approved', 'attendee_count' => $faker->numberBetween(2, max(2, $room->capacity)),
                        'ics_uid' => $uid(),
                    ]);
                    $hour = (int) ceil($e->hour + $e->minute / 60) + 1;
                }
            }
        }

        // Approval rooms: confirmed bookings and a queue of pending requests
        $execRooms = $rooms->where('requires_approval', true);
        foreach ($execRooms as $room) {
            foreach ([-12, -5, 2, 4, 9] as $off) {
                $day = $today->copy()->addDays($off);
                while ($day->isWeekend()) {
                    $day->addDay();
                }
                $creator = $host();
                $s = $day->copy()->setTime($faker->randomElement([9, 13]), 0);
                Booking::create([
                    'room_id' => $room->id, 'created_by' => $creator->id, 'approved_by' => $managers->first()->id, 'title' => $faker->randomElement(['Rapat Direksi', 'Town Hall', 'Review Kuartalan', 'Seminar Internal', 'Presentasi Investor']),
                    'start_time' => $s, 'end_time' => $s->copy()->addHours(2), 'status' => 'approved', 'attendee_count' => $faker->numberBetween(6, $room->capacity), 'ics_uid' => $uid(),
                ]);
            }
        }
        $pending = [['Merapi', 6, 10, 'Workshop Strategi 2027', 3], ['Semeru Boardroom', 7, 14, 'Rapat Dewan Komisaris', 2], ['Krakatau Auditorium', 11, 9, 'Gathering Akhir Tahun', 4], ['Merapi', 8, 13, 'Pelatihan Leadership', 3], ['Semeru Boardroom', 12, 10, 'Negosiasi Kontrak Vendor', 2]];
        foreach ($pending as [$name, $off, $hr, $title, $hours]) {
            $room = $rooms->firstWhere('name', $name);
            $day = $today->copy()->addDays($off);
            while ($day->isWeekend()) {
                $day->addDay();
            }
            $s = $day->copy()->setTime($hr, 0);
            if (Booking::where('room_id', $room->id)->where('start_time', '<', $s->copy()->addHours($hours + 1))->where('end_time', '>', $s->copy()->subHour())->exists()) {
                continue;
            }
            $creator = $staff->random();
            Booking::create([
                'room_id' => $room->id, 'created_by' => $creator->id, 'title' => $title, 'description' => 'Mohon persetujuan untuk penggunaan ruang eksekutif.',
                'start_time' => $s, 'end_time' => $s->copy()->addHours($hours), 'status' => 'pending', 'attendee_count' => min($room->capacity, 12), 'ics_uid' => $uid(),
            ]);
        }

        // Recurring: Monday engineering stand-up in Bromo for the next eight weeks
        $bromo = $rooms->firstWhere('name', 'Bromo');
        $series = RecurringBooking::create(['frequency' => 'weekly', 'interval' => 1, 'days_of_week' => 'Mon', 'end_date' => $today->copy()->addWeeks(8), 'created_by' => $managers->first()->id]);
        $monday = $today->copy()->next('Monday')->setTime(8, 30);
        for ($i = 0; $i < 8; $i++) {
            $s = $monday->copy()->addWeeks($i);
            Booking::where('room_id', $bromo->id)->where('start_time', '<', $s->copy()->addMinutes(30))->where('end_time', '>', $s)->delete();
            Booking::create([
                'room_id' => $bromo->id, 'created_by' => $managers->first()->id, 'recurring_booking_id' => $series->id, 'title' => 'Engineering Stand-up',
                'start_time' => $s, 'end_time' => $s->copy()->addMinutes(30), 'status' => 'approved', 'attendee_count' => 8, 'ics_uid' => $uid(),
                'attendees' => ['tim.engineering@' . $domain],
            ]);
        }

        // A few cancellations so the history looks lived in
        Booking::where('status', 'approved')->where('start_time', '>', now()->addDay())->inRandomOrder()->limit(5)
            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Agenda dipindahkan']);

        // A meeting running right now, so the live status board has something to show
        $nowRoom = $rooms->firstWhere('name', 'Prambanan');
        $start = now()->minute(now()->minute >= 30 ? 30 : 0)->second(0);
        Booking::where('room_id', $nowRoom->id)->where('start_time', '<', $start->copy()->addHour())->where('end_time', '>', $start)->delete();
        if ($start->isWeekday() && $start->hour >= 8 && $start->hour < 17) {
            Booking::create([
                'room_id' => $nowRoom->id, 'created_by' => $managers[1]->id, 'title' => 'Kick-off Kampanye Q1', 'start_time' => $start,
                'end_time' => $start->copy()->addHour(), 'status' => 'approved', 'attendee_count' => 10, 'ics_uid' => $uid(),
            ]);
        }
    }
}
