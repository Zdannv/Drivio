<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $drivers = User::where('role', 'driver')
            ->with(['trackingLogs' => function($q) {
                $q->latest()->take(1);
            }])
            ->get();

        return response()->json($drivers);
    }
}
