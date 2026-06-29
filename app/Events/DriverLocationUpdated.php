<?php

namespace App\Events;

use App\Models\TrackingLog;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a driver pushes a new GPS location.
 *
 * Carries the latest log + computed metadata so the admin dashboard
 * can dynamically refresh the corresponding map marker without polling.
 */
class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driverId;
    public string $driverName;
    public ?string $avatar;
    public float $latitude;
    public float $longitude;
    public string $loggedAt;
    public bool $isIdle;
    public ?float $idleDistanceMeters;
    public ?float $minutesSinceLastLog;
    public int $activeDeliveriesCount;

    public function __construct(
        User $driver,
        TrackingLog $log,
        bool $isIdle,
        ?float $idleDistanceMeters,
        ?float $minutesSinceLastLog,
        int $activeDeliveriesCount = 0
    ) {
        $this->driverId = $driver->id;
        $this->driverName = $driver->name;
        $this->avatar = $driver->avatar;
        $this->latitude = (float) $log->latitude;
        $this->longitude = (float) $log->longitude;
        $this->loggedAt = $log->created_at->toIso8601String();
        $this->isIdle = $isIdle;
        $this->idleDistanceMeters = $idleDistanceMeters;
        $this->minutesSinceLastLog = $minutesSinceLastLog;
        $this->activeDeliveriesCount = $activeDeliveriesCount;
    }

    /**
     * Broadcast on a private admin channel.
     * Anyone with role=admin can subscribe (see routes/channels.php).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.tracking'),
        ];
    }

    /**
     * Custom event name for the frontend listener.
     */
    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    /**
     * Payload shape sent over the wire.
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'driver_name' => $this->driverName,
            'avatar' => $this->avatar,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logged_at' => $this->loggedAt,
            'is_idle' => $this->isIdle,
            'idle_distance_meters' => $this->idleDistanceMeters,
            'minutes_since_last_log' => $this->minutesSinceLastLog,
            'active_deliveries_count' => $this->activeDeliveriesCount,
        ];
    }
}
