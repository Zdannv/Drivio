<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DeliveryController extends Controller
{
    /**
     * Admin view for delivering inertia frontend.
     */
    public function adminIndex(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        // FIXED: Ubah orderBy('id', 'asc') menjadi latest() agar tugas terbaru di atas
        $deliveries = Delivery::with(['driver', 'attendances' => function ($query) {
            $query->where('type', 'proof_of_delivery')->latest();
        }])->latest()->paginate(10);

        $today = \Carbon\Carbon::today();
        $drivers = \App\Models\User::where('role', 'driver')
            ->with(['attendances' => function ($query) use ($today) {
                $query->whereDate('created_at', $today)
                      ->whereIn('type', ['check_in', 'check_out']);
            }])
            ->get()
            ->map(function ($driver) {
                $hasCheckIn = $driver->attendances->contains('type', 'check_in');
                $hasCheckOut = $driver->attendances->contains('type', 'check_out');
                $driver->is_online = $hasCheckIn && !$hasCheckOut;
                unset($driver->attendances);
                return $driver;
            });

        return Inertia::render('Delivery/Index', [
            'deliveries' => $deliveries,
            'drivers'    => $drivers
        ]);
    }

    /**
     * Fetch all deliveries for admin, or just the assigned user's for driver.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // FIXED: Tambahkan relasi attendances di sini agar saat request JSON, data PoD tetap terbawa
        if ($user->role === 'admin') {
            $deliveries = Delivery::with(['driver', 'attendances' => function ($query) {
                $query->where('type', 'proof_of_delivery')->latest();
            }])->latest()->get();
        } else {
            $deliveries = Delivery::with(['driver', 'attendances' => function ($query) {
                $query->where('type', 'proof_of_delivery')->latest();
            }])->where('driver_id', $user->id)->latest()->get();
        }

        return response()->json($deliveries);
    }

    /**
     * Admin creates a delivery.
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'driver_id'           => 'required|exists:users,id',
            'destination_address' => 'required|string',
            'destination_lat'     => 'required|numeric',
            'destination_lng'     => 'required|numeric',
            'items'               => 'nullable|string|max:1000',
        ]);

        $validated['status'] = 'pending';

        $delivery = Delivery::create($validated);

        return redirect()->back()->with('success', 'Delivery dispatched successfully.');
    }

    /**
     * Display the specific delivery details.
     */
    public function show(Delivery $delivery)
    {
        $delivery->load(['driver', 'trackingLogs', 'attendances' => function ($query) {
            $query->latest();
        }]);

        // Calculate distance via tracking logs
        $distance = 0;
        $logs = $delivery->trackingLogs()->orderBy('created_at', 'asc')->get();
        if ($logs->count() > 1) {
            for ($i = 0; $i < $logs->count() - 1; $i++) {
                $distance += $this->haversineDistance(
                    (float)$logs[$i]->latitude, (float)$logs[$i]->longitude,
                    (float)$logs[$i+1]->latitude, (float)$logs[$i+1]->longitude
                );
            }
        }

        // Calculate duration
        $duration = null;
        if ($delivery->started_at && $delivery->completed_at) {
            $duration = $delivery->started_at->diffForHumans($delivery->completed_at, true, false, 3);
        }

        return response()->json([
            'delivery' => $delivery,
            'distance' => round($distance, 2), // km
            'duration' => $duration
        ]);
    }

    /**
     * Update delivery status.
     */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $delivery->driver_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,on_way,completed',
        ]);

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'on_way' && !$delivery->started_at) {
            $updateData['started_at'] = now();
        } elseif ($validated['status'] === 'completed') {
            // Ensure PoD exists before completion
            $hasPod = $delivery->attendances()->where('type', 'proof_of_delivery')->exists();
            if (!$hasPod) {
                return response()->json([
                    'message' => 'Cannot complete delivery without a valid Proof of Delivery (PoD).'
                ], 403);
            }
            $updateData['completed_at'] = now();
        }

        $delivery->update($updateData);

        return response()->json($delivery);
    }

    /**
     * Haversine distance formula to calculate distance between two points on Earth.
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}