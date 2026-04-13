<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('User/Index', [
            'users' => User::where('role', 'driver')->latest()->paginate(10)
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

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.',email,'.$user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'face_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('face_image')) {
            if ($user->face_image_path) {
                Storage::disk('public')->delete($user->face_image_path);
            }
            $user->face_image_path = $request->file('face_image')->store('driver_faces', 'public');
        }

        $user->save();

        return redirect()->back()->with('success', 'Driver updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->face_image_path) {
            Storage::disk('public')->delete($user->face_image_path);
        }
        
        $user->delete();

        return redirect()->back()->with('success', 'Driver deleted successfully.');
    }
}