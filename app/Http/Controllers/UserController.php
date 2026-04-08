<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('User/Index', [
            'users' => User::where('role', 'driver')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'face_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $path = $request->file('face_image')->store('driver_faces', 'public');

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => 'driver',
            'password' => Hash::make($request->password),
            'face_image_path' => $path,
        ]);

        return redirect()->back()->with('success', 'Driver registered successfully.');
    }
}