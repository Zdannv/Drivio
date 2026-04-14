<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user && $user->role === 'admin') {
            return Inertia::render('Admin/Dashboard');
        }

        $today = Carbon::today();
        $isCheckedIn = Attendance::where('user_id', $user->id)
                        ->where('type', 'check_in')
                        ->whereDate('created_at', $today)
                        ->exists();

        $hasCheckedOut = Attendance::where('user_id', $user->id)
                        ->where('type', 'check_out')
                        ->whereDate('created_at', $today)
                        ->exists();

        return Inertia::render('Driver/Dashboard', [
            'isCheckedIn' => $isCheckedIn,
            'hasCheckedOut' => $hasCheckedOut
        ]);
    }
}
