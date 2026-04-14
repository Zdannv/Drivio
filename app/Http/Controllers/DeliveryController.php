<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\Request;

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

        $deliveries = Delivery::with(['driver', 'attendances' => function ($query) {
            $query->where('type', 'proof_of_delivery')->latest();
        }])->orderBy('id', 'asc')->paginate(10);

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

        return inertia('Delivery/Index', [
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

        if ($user->role === 'admin') {
            $deliveries = Delivery::with('driver')->get();
        } else {
            $deliveries = Delivery::with('driver')->where('driver_id', $user->id)->get();
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

        // Convert the API response back to an Inertia-compatible redirect
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
