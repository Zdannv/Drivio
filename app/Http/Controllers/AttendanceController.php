<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     * TO BE IMPLEMENTED in Phase 3.
     */
    public function index(Request $request)
    {
        $users = Cache::remember('all_users_list', 1800, function () {
            return User::orderBy('name')->get();
        });

        $attendances = Attendance::with('user', 'delivery')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Attendance/Index', compact('attendances', 'users'));
    }

    /**
     * Store a new attendance record (face verification + GPS).
     * TO BE IMPLEMENTED in Phase 3.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image'     => 'required|string',
            'type'      => 'required|in:check_in,check_out,proof_of_delivery',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Phase 3 will wire the Python face verification service here.
        return response()->json([
            'status'  => 'error',
            'message' => 'Attendance store not yet implemented. Coming in Phase 3.',
        ], 501);
    }

    /**
     * Export attendance records.
     * TO BE IMPLEMENTED in Phase 3.
     */
    public function export(Request $request)
    {
        return response()->json(['message' => 'Export not yet implemented.'], 501);
    }

    /**
     * Toggle the global attendance feature on/off.
     */
    public function toggleStatus(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        Cache::forever('attendance_enabled', $request->is_enabled);

        $status = $request->is_enabled ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Fitur absensi berhasil $status.");
    }

    /**
     * Reverse geocode a coordinate pair to a human-readable address.
     */
    private function getAddress($latitude, $longitude): ?string
    {
        if (!$latitude || !$longitude) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'LogisticsApp/1.0'])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat'    => $latitude,
                    'lon'    => $longitude,
                ]);

            if ($response->successful()) {
                return $response->json()['display_name'] ?? null;
            }
        } catch (\Exception $e) {
            \Log::error('Geocoding error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Verify a face image against stored embeddings via Python service.
     */
    private function verifyFace(string $imageBase64): array
    {
        $host = env('PYTHON_SERVICE');
        $port = env('PYTHON_SERVICE_PORT');

        $users = User::whereNotNull('face_embedding')->get(['id', 'name', 'face_embedding']);

        $userData = $users->map(fn($u) => [
            'id'        => $u->id,
            'name'      => $u->name,
            'embedding' => $u->face_embedding,
        ]);

        try {
            $response = Http::post("http://{$host}:{$port}/attendance", [
                'image' => $imageBase64,
                'users' => $userData,
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['match'])) {
                return [
                    'success'    => $result['match'],
                    'user_id'    => $result['message'] ?? null,
                    'confidence' => $result['confidence'] ?? 0,
                ];
            }

            return [
                'success'    => false,
                'message'    => $result['message'] ?? 'Wajah tidak dikenali',
                'confidence' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Gagal koneksi ke server: ' . $e->getMessage(),
                'confidence' => 0,
            ];
        }
    }
}