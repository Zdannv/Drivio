macbook@MacBook-Pro Drivio % ./vendor/bin/sail down

[+] Running 7/7
 ⠿ Container drivio-laravel.test-1    Removed                     6.5s
 ⠿ Container drivio-mailpit-1         Removed                     1.2s
 ⠿ Container drivio-redis-1           Removed                     1.2s
 ⠿ Container drivio-pma-1             Removed                     3.0s
 ⠿ Container drivio-face_recognize-1  Removed                     6.6s
 ⠿ Container drivio-mysql-1           Removed                     5.4s
 ⠿ Network drivio_sail                Re...                       0.8s
macbook@MacBook-Pro Drivio % sail up -d
[+] Running 7/7
 ⠿ Network drivio_sail                Cr...                       0.3s
 ⠿ Container drivio-mysql-1           Started                     8.4s
 ⠿ Container drivio-redis-1           Started                     9.1s
 ⠿ Container drivio-mailpit-1         Started                     8.1s
 ⠿ Container drivio-pma-1             Started                     8.7s
 ⠿ Container drivio-face_recognize-1  Started                     8.7s
 ⠿ Container drivio-laravel.test-1    Started                     9.3s
macbook@MacBook-Pro Drivio % sail composer require laravel/reverb

The repository at "/var/www/html" does not have the correct ownership and git refuses to use it:

fatal: detected dubious ownership in repository at '/var/www/html'
To add an exception for this directory, call:

git config --global --add safe.directory /var/www/html

./composer.json has been updated
The repository at "/var/www/html" does not have the correct ownership and git refuses to use it:

fatal: detected dubious ownership in repository at '/var/www/html'
To add an exception for this directory, call:

git config --global --add safe.directory /var/www/html

Running composer update laravel/reverb
Loading composer repositories with package information
Updating dependencies
Lock file operations: 13 installs, 0 updates, 0 removals
  - Locking clue/redis-protocol (v0.3.2)
  - Locking clue/redis-react (v2.8.0)
  - Locking evenement/evenement (v3.0.2)
  - Locking laravel/reverb (v1.10.2)
  - Locking pusher/pusher-php-server (7.2.8)
  - Locking ratchet/rfc6455 (v0.4.0)
  - Locking react/cache (v1.2.0)
  - Locking react/dns (v1.14.0)
  - Locking react/event-loop (v1.6.0)
  - Locking react/promise (v3.3.0)
  - Locking react/promise-timer (v1.11.0)
  - Locking react/socket (v1.17.0)
  - Locking react/stream (v1.4.0)
Writing lock file
Installing dependencies from lock file (including require-dev)
Package operations: 13 installs, 0 updates, 0 removals
  - Downloading clue/redis-protocol (v0.3.2)
  - Downloading react/event-loop (v1.6.0)
  - Downloading evenement/evenement (v3.0.2)
  - Downloading react/stream (v1.4.0)
  - Downloading react/promise (v3.3.0)
  - Downloading react/cache (v1.2.0)
  - Downloading react/dns (v1.14.0)
  - Downloading react/socket (v1.17.0)
  - Downloading react/promise-timer (v1.11.0)
  - Downloading ratchet/rfc6455 (v0.4.0)
  - Downloading pusher/pusher-php-server (7.2.8)
  - Downloading clue/redis-react (v2.8.0)
  - Downloading laravel/reverb (v1.10.2)
  - Installing clue/redis-protocol (v0.3.2): Extracting archive
  - Installing react/event-loop (v1.6.0): Extracting archive
  - Installing evenement/evenement (v3.0.2): Extracting archive
  - Installing react/stream (v1.4.0): Extracting archive
  - Installing react/promise (v3.3.0): Extracting archive
  - Installing react/cache (v1.2.0): Extracting archive
  - Installing react/dns (v1.14.0): Extracting archive
  - Installing react/socket (v1.17.0): Extracting archive
  - Installing react/promise-timer (v1.11.0): Extracting archive
  - Installing ratchet/rfc6455 (v0.4.0): Extracting archive
  - Installing pusher/pusher-php-server (7.2.8): Extracting archive
  - Installing clue/redis-react (v2.8.0): Extracting archive
  - Installing laravel/reverb (v1.10.2): Extracting archive
Generating optimized autoload files
> Illuminate\Foundation\ComposerScripts::postAutoloadDump
> @php artisan package:discover --ansi

   INFO  Discovering packages.  

  inertiajs/inertia-laravel .................................... DONE
  laravel/breeze ............................................... DONE
  laravel/pail ................................................. DONE
  laravel/reverb ............................................... DONE
  laravel/sail ................................................. DONE
  laravel/sanctum .............................................. DONE
  laravel/tinker ............................................... DONE
  maatwebsite/excel ............................................ DONE
  nesbot/carbon ................................................ DONE
  nunomaduro/collision ......................................... DONE
  nunomaduro/termwind .......................................... DONE
  tightenco/ziggy .............................................. DONE

94 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
> @php artisan vendor:publish --tag=laravel-assets --ansi --force

   INFO  No publishable resources for tag [laravel-assets].  

Found 18 security vulnerability advisories affecting 10 packages.
Run "composer audit" for a full list of advisories.
Using version ^1.10 for laravel/reverb
macbook@MacBook-Pro Drivio % sail artisan install:broadcasting

   ERROR  The 'broadcasting' configuration file already exists.  

 ┌ Which broadcasting driver would you like to use? ────────────┐
 │ Laravel Reverb                                               │
 └──────────────────────────────────────────────────────────────┘

   WARN  Could not find file [resources/js/app.ts]. Skipping automatic Echo configuration.  

 ┌ Would you like to install and build the Node dependencies requir… ┐
 │ Yes                                                               │
 └───────────────────────────────────────────────────────────────────┘

   INFO  Installing and building Node dependencies.  

yarn add v1.22.22
warning package-lock.json found. Your project contains lock files generated by tools other than Yarn. It is advised not to mix package managers in order to avoid resolution inconsistencies caused by unsynchronized lock files. To clear this warning, remove package-lock.json.
[1/4] Resolving packages...
[2/4] Fetching packages...
[####################################################--------] 327/376
   Illuminate\Process\Exceptions\ProcessTimedOutException 

  The process "yarn add --dev laravel-echo pusher-js @laravel/echo-vue && yarn run build" exceeded the timeout of 60 seconds.

  at vendor/laravel/framework/src/Illuminate/Process/PendingProcess.php:260
    256▕             }
    257▕ 
    258▕             return new ProcessResult(tap($process)->run($output));
    259▕         } catch (SymfonyTimeoutException $e) {
  ➜ 260▕             throw new ProcessTimedOutException($e, new ProcessResult($process));
    261▕         }
    262▕     }
    263▕ 
    264▕     /**

      +20 vendor frames 

  21  artisan:16
      Illuminate\Foundation\Application::handleCommand()

[#####################################################-------] 330/376%
macbook@MacBook-Pro Drivio % <?php

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
