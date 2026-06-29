<?php

namespace App\Http\Controllers;

use App\Events\DriverIdleDetected;
use App\Events\DriverLocationUpdated;
use App\Models\User;
use App\Models\TrackingLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    /**
     * Store a new tracking log entry for the authenticated driver.
     *
     * Side effects:
     *  - Persists a TrackingLog row.
     *  - Broadcasts DriverLocationUpdated for every successful save.
     *  - Broadcasts DriverIdleDetected when the driver transitions
     *    from active -> idle on this push (edge-triggered).
     */
    public function store(Request $request): JsonResponse
    {
        // Validate user is a driver
        if ($request->user()->role !== 'driver') {
            abort(403, 'Only drivers can submit tracking data');
        }

        // Validate request data
        $validated = $request->validate([
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'delivery_id' => 'nullable|exists:deliveries,id',
        ]);

        $driver = $request->user();

        // Capture idle state BEFORE this new log is saved.
        // Used to detect the active -> idle edge transition.
        $wasIdleBefore = $this->calculateIdleStatus($driver);

        // Create tracking log
        $trackingLog = TrackingLog::create([
            'driver_id' => $driver->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'delivery_id' => $validated['delivery_id'] ?? null,
        ]);

        // Refresh relation so subsequent calculations include the new log
        $driver->unsetRelation('trackingLogs');

        // Recompute idle + metadata using the existing logic
        $isIdleNow = $this->calculateIdleStatus($driver);
        $metadata = $this->calculateMetadata($driver);

        $activeDeliveriesCount = $driver->deliveries()
            ->whereIn('status', ['pending', 'on_way'])
            ->count();

        // Always broadcast the location update so the map can refresh in real time
        broadcast(new DriverLocationUpdated(
            $driver,
            $trackingLog,
            $isIdleNow,
            $metadata['idle_distance_meters'],
            $metadata['minutes_since_last_log'],
            $activeDeliveriesCount
        ));

        // Edge-triggered idle alert: only fire on the active -> idle transition
        if ($isIdleNow && !$wasIdleBefore) {
            broadcast(new DriverIdleDetected(
                $driver,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                $metadata['idle_distance_meters'],
                $metadata['minutes_since_last_log']
            ));
        }

        return response()->json($trackingLog, 201);
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $drivers = User::where('role', 'driver')
            ->with(['trackingLogs' => function ($q) {
                $q->latest()->take(1);
            }])
            ->withCount(['deliveries as active_deliveries_count' => function ($q) {
                $q->whereIn('status', ['pending', 'on_way']);
            }])
            ->get();

        // Calculate and append idle status and metadata for each driver
        $drivers->each(function ($driver) {
            $driver->is_idle = $this->calculateIdleStatus($driver);

            // Add metadata
            $metadata = $this->calculateMetadata($driver);
            $driver->idle_distance_meters = $metadata['idle_distance_meters'];
            $driver->minutes_since_last_log = $metadata['minutes_since_last_log'];
        });

        return response()->json($drivers);
    }

    /**
     * Calculate the distance between two GPS coordinates using the Haversine formula.
     *
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @return float Distance in meters
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth's radius in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Distance in meters
    }

    /**
     * Calculate whether a driver is idle based on their tracking logs.
     * A driver is considered idle if:
     * 1. They have tracking logs AND
     * 2. They have moved less than the configured distance threshold in the time window
     *
     * Special cases:
     * - If latest log is older than time window but exists = IDLE (no recent movement)
     * - If only 1 log in time window = IDLE (staying in one place)
     * - If multiple logs but distance < threshold = IDLE (minimal movement)
     *
     * @param User $driver The driver to check
     * @return bool True if idle, false otherwise
     */
    private function calculateIdleStatus(User $driver): bool
    {
        // Get the latest tracking log
        $latestLog = $driver->trackingLogs()->latest()->first();

        // No logs at all = not idle (no data to determine)
        if (!$latestLog) {
            return false;
        }

        $idleMinutes = config('tracking.idle_minutes');
        $idleMeters = config('tracking.idle_meters');
        $timeWindowStart = now()->subMinutes($idleMinutes);

        // Fetch all logs from the configured time window
        $recentLogs = $driver->trackingLogs()
            ->where('created_at', '>=', $timeWindowStart)
            ->orderBy('created_at', 'asc')
            ->get();

        // No logs in time window (latest log is old) = IDLE (driver hasn't moved/updated)
        if ($recentLogs->count() === 0) {
            return true;
        }

        // Only 1 log in time window = IDLE (not moving, just staying in one place)
        if ($recentLogs->count() === 1) {
            return true;
        }

        // Calculate total distance moved
        $totalDistance = 0;
        for ($i = 1; $i < $recentLogs->count(); $i++) {
            $totalDistance += $this->haversineDistance(
                (float) $recentLogs[$i - 1]->latitude,
                (float) $recentLogs[$i - 1]->longitude,
                (float) $recentLogs[$i]->latitude,
                (float) $recentLogs[$i]->longitude
            );
        }

        // Idle if moved less than configured distance threshold
        return $totalDistance < $idleMeters;
    }

    /**
     * Calculate movement metadata for a driver.
     *
     * @param User $driver The driver to calculate metadata for
     * @return array{idle_distance_meters: float|null, minutes_since_last_log: float|null}
     */
    private function calculateMetadata(User $driver): array
    {
        $latestLog = $driver->trackingLogs()->latest()->first();

        // No logs = null metadata
        if (!$latestLog) {
            return [
                'idle_distance_meters' => null,
                'minutes_since_last_log' => null,
            ];
        }

        // Calculate minutes since last log
        $minutesSinceLastLog = $latestLog->created_at->diffInMinutes(now());

        // Calculate distance moved within time window
        $idleMinutes = config('tracking.idle_minutes');
        $timeWindowStart = now()->subMinutes($idleMinutes);

        $recentLogs = $driver->trackingLogs()
            ->where('created_at', '>=', $timeWindowStart)
            ->orderBy('created_at', 'asc')
            ->get();

        // Need at least 2 points to calculate distance
        if ($recentLogs->count() < 2) {
            $idleDistanceMeters = 0.0;
        } else {
            $totalDistance = 0;
            for ($i = 1; $i < $recentLogs->count(); $i++) {
                $totalDistance += $this->haversineDistance(
                    (float) $recentLogs[$i - 1]->latitude,
                    (float) $recentLogs[$i - 1]->longitude,
                    (float) $recentLogs[$i]->latitude,
                    (float) $recentLogs[$i]->longitude
                );
            }
            $idleDistanceMeters = $totalDistance;
        }

        return [
            'idle_distance_meters' => round($idleDistanceMeters, 2),
            'minutes_since_last_log' => $minutesSinceLastLog,
        ];
    }
}
