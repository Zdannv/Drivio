<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a driver TRANSITIONS into the idle state.
 *
 * Distinct from DriverLocationUpdated: this fires only on the active->idle edge
 * so the admin dashboard can show a one-shot warning toast instead of repeating
 * for every subsequent stationary log.
 */
class DriverIdleDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driverId;
    public string $driverName;
    public ?string $avatar;
    public float $latitude;
    public float $longitude;
    public ?float $idleDistanceMeters;
    public ?float $minutesSinceLastLog;
    public string $detectedAt;

    public function __construct(
        User $driver,
        float $latitude,
        float $longitude,
        ?float $idleDistanceMeters,
        ?float $minutesSinceLastLog
    ) {
        $this->driverId = $driver->id;
        $this->driverName = $driver->name;
        $this->avatar = $driver->avatar;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->idleDistanceMeters = $idleDistanceMeters;
        $this->minutesSinceLastLog = $minutesSinceLastLog;
        $this->detectedAt = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.tracking'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver.idle.detected';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'driver_name' => $this->driverName,
            'avatar' => $this->avatar,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'idle_distance_meters' => $this->idleDistanceMeters,
            'minutes_since_last_log' => $this->minutesSinceLastLog,
            'detected_at' => $this->detectedAt,
        ];
    }
}
