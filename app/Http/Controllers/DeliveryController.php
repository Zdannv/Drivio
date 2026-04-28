<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
        ]);

        $validated['status'] = 'pending';

        $delivery = Delivery::create($validated);

        return redirect()->back()->with('success', 'Delivery dispatched successfully.');
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

        $delivery->update(['status' => $validated['status']]);

        return response()->json($delivery);
    }
}