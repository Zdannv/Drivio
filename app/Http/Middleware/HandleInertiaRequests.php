<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],

            // Menambahkan status fitur absensi secara global ke frontend
            'attendance_enabled' => Cache::get('attendance_enabled', true),

            'flash' => fn () => [
                'success' => session()->pull('success'),
                'error' => session()->pull('error'),
                'info' => session()->pull('info'),
                'warning' => session()->pull('warning'),
            ],
        ];
    }
}