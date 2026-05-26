<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Delivery;
use App\Models\Attendance;
use App\Models\TrackingLog;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class BuildingMaterialStoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Admin
        $admin = User::create([
            'name' => 'Admin Toko Bangunan',
            'email' => 'admin@tokobangunan.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'avatar' => '/avatars/1.png',
        ]);

        // 2. Create 6 Drivers (nama khas Surabaya)
        $drivers = [
            ['name' => 'Budi Santoso', 'email' => 'budi@tokobangunan.com', 'avatar' => '/avatars/2.png'],
            ['name' => 'Agus Wijaya', 'email' => 'agus@tokobangunan.com', 'avatar' => '/avatars/3.png'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@tokobangunan.com', 'avatar' => '/avatars/4.png'],
            ['name' => 'Dedi Kurniawan', 'email' => 'dedi@tokobangunan.com', 'avatar' => '/avatars/5.png'],
            ['name' => 'Hendra Gunawan', 'email' => 'hendra@tokobangunan.com', 'avatar' => '/avatars/6.png'],
            ['name' => 'Rizki Firmansyah', 'email' => 'rizki@tokobangunan.com', 'avatar' => '/avatars/7.png'],
        ];

        $driverModels = [];
        foreach ($drivers as $driver) {
            $driverModels[] = User::create([
                'name' => $driver['name'],
                'email' => $driver['email'],
                'password' => Hash::make('password'),
                'role' => 'driver',
                'avatar' => $driver['avatar'],
            ]);
        }

        // 3. Create Attendance Records (Check-in for today)
        $today = Carbon::today();
        foreach ($driverModels as $index => $driver) {
            // 4 drivers sudah check-in (online)
            if ($index < 4) {
                Attendance::create([
                    'user_id' => $driver->id,
                    'type' => 'check_in',
                    'latitude' => -7.2504 + (rand(-100, 100) / 10000),
                    'longitude' => 112.7688 + (rand(-100, 100) / 10000),
                    'address' => 'Toko Bangunan Jaya, Jl. Raya Darmo No. 123, Surabaya',
                    'created_at' => $today->copy()->addHours(7)->addMinutes(rand(0, 30)),
                ]);
            }
            // 1 driver sudah check-out (offline)
            if ($index === 4) {
                Attendance::create([
                    'user_id' => $driver->id,
                    'type' => 'check_in',
                    'latitude' => -7.2504,
                    'longitude' => 112.7688,
                    'address' => 'Toko Bangunan Jaya, Jl. Raya Darmo No. 123, Surabaya',
                    'created_at' => $today->copy()->addHours(7),
                ]);
                Attendance::create([
                    'user_id' => $driver->id,
                    'type' => 'check_out',
                    'latitude' => -7.2504,
                    'longitude' => 112.7688,
                    'address' => 'Toko Bangunan Jaya, Jl. Raya Darmo No. 123, Surabaya',
                    'created_at' => $today->copy()->addHours(16),
                ]);
            }
            // 1 driver belum check-in (offline)
        }

        // 4. Lokasi-lokasi di Surabaya untuk delivery
        $surabayaLocations = [
            ['address' => 'Perumahan Griya Kebraon Indah Blok A No. 15, Surabaya', 'lat' => -7.3207, 'lng' => 112.6689],
            ['address' => 'Jl. Rungkut Asri Utara No. 45, Surabaya', 'lat' => -7.3186, 'lng' => 112.7809],
            ['address' => 'Komplek Ruko Dharmahusada Mas Blok B-12, Surabaya', 'lat' => -7.2819, 'lng' => 112.7603],
            ['address' => 'Perumahan Citra Harmoni Blok C No. 8, Sidoarjo', 'lat' => -7.4479, 'lng' => 112.7186],
            ['address' => 'Jl. Mayjend Sungkono No. 89, Surabaya', 'lat' => -7.2889, 'lng' => 112.7319],
            ['address' => 'Perumahan Pakuwon City Blok AA No. 23, Surabaya', 'lat' => -7.2989, 'lng' => 112.6789],
            ['address' => 'Jl. Ahmad Yani No. 156, Surabaya', 'lat' => -7.3169, 'lng' => 112.7289],
            ['address' => 'Komplek Pergudangan Margomulyo Blok D-5, Surabaya', 'lat' => -7.2419, 'lng' => 112.6289],
            ['address' => 'Perumahan Galaxy Bumi Permai Blok F No. 12, Surabaya', 'lat' => -7.3389, 'lng' => 112.7689],
            ['address' => 'Jl. Raya Kalirungkut No. 78, Surabaya', 'lat' => -7.3289, 'lng' => 112.7889],
        ];

        // 5. Bahan bangunan yang umum
        $buildingMaterials = [
            '50 sak Semen Gresik, 1000 bata merah',
            '100 batang besi beton 10mm, 20 sak pasir',
            '5 pintu kayu jati, 3 jendela aluminium',
            '200 genteng keramik, 10 sak semen putih',
            '15 lembar triplek 18mm, 20 batang kayu balok 6x12',
            '50 kg paku berbagai ukuran, 10 kaleng cat tembok',
            '2 unit closet duduk TOTO, 1 bak mandi fiber',
            '100 meter pipa PVC 3 inch, 50 sambungan pipa',
            '20 sak semen instan, 500 bata ringan',
            '10 pintu PVC, 5 rolling door',
            '30 lembar gypsum board, 50 meter rangka hollow',
            '5 drum cat besi, 10 kaleng thinner',
            '200 keramik lantai 40x40, 50 sak perekat keramik',
            '15 batang besi hollow 4x4, 20 batang besi siku',
            '100 meter kabel listrik NYM, 50 fitting lampu',
        ];

        // 6. Create Deliveries dengan berbagai status
        $deliveryStatuses = [
            // Completed deliveries (kemarin dan hari ini)
            ['status' => 'completed', 'days_ago' => 0, 'count' => 3],
            ['status' => 'completed', 'days_ago' => 1, 'count' => 5],
            ['status' => 'completed', 'days_ago' => 2, 'count' => 4],
            // On way deliveries (hari ini)
            ['status' => 'on_way', 'days_ago' => 0, 'count' => 2],
            // Pending deliveries (hari ini)
            ['status' => 'pending', 'days_ago' => 0, 'count' => 3],
        ];

        $deliveryId = 1;
        foreach ($deliveryStatuses as $statusGroup) {
            for ($i = 0; $i < $statusGroup['count']; $i++) {
                $location = $surabayaLocations[array_rand($surabayaLocations)];
                $driver = $driverModels[array_rand($driverModels)];
                $material = $buildingMaterials[array_rand($buildingMaterials)];
                
                $createdAt = Carbon::today()->subDays($statusGroup['days_ago'])->addHours(rand(8, 16))->addMinutes(rand(0, 59));
                
                $deliveryData = [
                    'driver_id' => $driver->id,
                    'destination_address' => $location['address'],
                    'destination_lat' => $location['lat'],
                    'destination_lng' => $location['lng'],
                    'items' => $material,
                    'status' => $statusGroup['status'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if ($statusGroup['status'] === 'on_way' || $statusGroup['status'] === 'completed') {
                    $deliveryData['started_at'] = $createdAt->copy()->addMinutes(rand(10, 30));
                }

                if ($statusGroup['status'] === 'completed') {
                    $deliveryData['completed_at'] = $createdAt->copy()->addHours(rand(1, 3));
                }

                $delivery = Delivery::create($deliveryData);

                // 7. Create Tracking Logs untuk delivery yang on_way atau completed
                if ($statusGroup['status'] === 'on_way' || $statusGroup['status'] === 'completed') {
                    $this->createTrackingLogs($delivery, $driver, $location);
                }

                // 8. Create Proof of Delivery untuk completed deliveries
                if ($statusGroup['status'] === 'completed') {
                    Attendance::create([
                        'user_id' => $driver->id,
                        'delivery_id' => $delivery->id,
                        'type' => 'proof_of_delivery',
                        'latitude' => $location['lat'] + (rand(-10, 10) / 10000),
                        'longitude' => $location['lng'] + (rand(-10, 10) / 10000),
                        'address' => $location['address'],
                        'face_similarity_score' => rand(85, 99) / 100,
                        'created_at' => $delivery->completed_at,
                    ]);
                }

                $deliveryId++;
            }
        }

        $this->command->info('✅ Mock data toko bangunan berhasil dibuat!');
        $this->command->info('📦 Total: ' . count($driverModels) . ' drivers, ' . Delivery::count() . ' deliveries');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin: admin@tokobangunan.com / password');
        $this->command->info('   Driver: budi@tokobangunan.com / password (dan driver lainnya)');
    }

    /**
     * Create realistic tracking logs for a delivery
     */
    private function createTrackingLogs($delivery, $driver, $destination)
    {
        // Starting point (toko bangunan)
        $startLat = -7.2504;
        $startLng = 112.7688;
        
        // Destination
        $endLat = $destination['lat'];
        $endLng = $destination['lng'];

        // Create 10-15 tracking points between start and destination
        $numPoints = rand(10, 15);
        $startTime = $delivery->started_at;

        for ($i = 0; $i <= $numPoints; $i++) {
            $progress = $i / $numPoints;
            
            // Interpolate between start and end with some randomness
            $lat = $startLat + ($endLat - $startLat) * $progress + (rand(-20, 20) / 10000);
            $lng = $startLng + ($endLng - $startLng) * $progress + (rand(-20, 20) / 10000);
            
            // Add time progression (2-5 minutes between points)
            $timestamp = $startTime->copy()->addMinutes($i * rand(2, 5));
            
            // Don't create logs beyond completed_at
            if ($delivery->completed_at && $timestamp->gt($delivery->completed_at)) {
                break;
            }

            TrackingLog::create([
                'driver_id' => $driver->id,
                'delivery_id' => $delivery->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }
}
