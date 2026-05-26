<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'items',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination_lat' => 'decimal:8',
            'destination_lng' => 'decimal:8',
            'started_at'      => 'datetime',
            'completed_at'    => 'datetime',
        ];
    }

    /**
     * The driver assigned to this delivery.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Attendance records (check-ins / proof of delivery) for this delivery.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * GPS tracking breadcrumbs for this delivery.
     */
    public function trackingLogs()
    {
        return $this->hasMany(TrackingLog::class);
    }
}
