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
            // Fetch analytics data for admin dashboard
            $analytics = $this->getDriverAnalytics();
            
            return Inertia::render('Admin/Dashboard', [
                'analytics' => $analytics
            ]);
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

    private function getDriverAnalytics()
    {
        $today = Carbon::today();
        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisMonthStart = Carbon::now()->startOfMonth();

        // Total drivers
        $totalDrivers = User::where('role', 'driver')->count();

        // Active today (checked in)
        $activeToday = Attendance::where('type', 'check_in')
            ->whereDate('created_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        // Pending verification (deliveries with status pending)
        $pendingVerification = \App\Models\Delivery::where('status', 'pending')->count();

        // Deliveries stats
        $deliveriesToday = \App\Models\Delivery::whereDate('created_at', $today)->count();
        $deliveriesThisWeek = \App\Models\Delivery::where('created_at', '>=', $thisWeekStart)->count();
        $deliveriesThisMonth = \App\Models\Delivery::where('created_at', '>=', $thisMonthStart)->count();

        // Completed deliveries stats
        $completedToday = \App\Models\Delivery::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->count();
        $completedThisWeek = \App\Models\Delivery::where('status', 'completed')
            ->where('completed_at', '>=', $thisWeekStart)
            ->count();
        $completedThisMonth = \App\Models\Delivery::where('status', 'completed')
            ->where('completed_at', '>=', $thisMonthStart)
            ->count();

        // Top performers today (drivers with most completed deliveries)
        $topPerformersToday = User::where('role', 'driver')
            ->withCount(['deliveries as completed_today' => function($q) use ($today) {
                $q->where('status', 'completed')
                  ->whereDate('completed_at', $today);
            }])
            ->having('completed_today', '>', 0)
            ->orderBy('completed_today', 'desc')
            ->take(5)
            ->get(['id', 'name', 'avatar'])
            ->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'avatar' => $driver->avatar,
                    'completed_count' => $driver->completed_today
                ];
            });

        // Driver status (current status of all drivers)
        $driverStatus = User::where('role', 'driver')
            ->with(['trackingLogs' => function($q) {
                $q->latest()->take(1);
            }])
            ->withCount(['deliveries as active_deliveries' => function($q) {
                $q->whereIn('status', ['pending', 'on_way']);
            }])
            ->get(['id', 'name', 'avatar', 'is_online'])
            ->map(function($driver) use ($today) {
                $isCheckedIn = Attendance::where('user_id', $driver->id)
                    ->where('type', 'check_in')
                    ->whereDate('created_at', $today)
                    ->exists();

                $hasCheckedOut = Attendance::where('user_id', $driver->id)
                    ->where('type', 'check_out')
                    ->whereDate('created_at', $today)
                    ->exists();

                // Determine status
                $status = 'offline';
                if ($isCheckedIn && !$hasCheckedOut) {
                    $status = $driver->active_deliveries > 0 ? 'busy' : 'available';
                }

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'avatar' => $driver->avatar,
                    'status' => $status,
                    'active_deliveries' => $driver->active_deliveries,
                    'has_tracking' => $driver->trackingLogs->isNotEmpty()
                ];
            });

        return [
            'overview' => [
                'total_drivers' => $totalDrivers,
                'active_today' => $activeToday,
                'pending_verification' => $pendingVerification,
            ],
            'deliveries' => [
                'today' => $deliveriesToday,
                'this_week' => $deliveriesThisWeek,
                'this_month' => $deliveriesThisMonth,
            ],
            'completed' => [
                'today' => $completedToday,
                'this_week' => $completedThisWeek,
                'this_month' => $completedThisMonth,
            ],
            'top_performers_today' => $topPerformersToday,
            'driver_status' => $driverStatus,
        ];
    }
}
