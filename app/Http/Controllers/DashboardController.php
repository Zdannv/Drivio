<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user && $user->role === 'admin') {
            return Inertia::render('Admin/Dashboard');
        }

        return Inertia::render('Driver/Dashboard');
    }
}
