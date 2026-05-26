<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TrackingLog;
use App\Models\Delivery;
use App\Models\User;
use App\Services\FaceRecognizeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     * TO BE IMPLEMENTED in Phase 3.
     */
    public function index(Request $request)
    {
        $query = Attendance::with('user', 'delivery')
            ->whereIn('type', ['check_in', 'check_out']);

        if ($request->filled('search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('from') && $request->filled('to')) {
            $from = \Carbon\Carbon::createFromFormat('d-m-Y', $request->from)->startOfDay();
            $to = \Carbon\Carbon::createFromFormat('d-m-Y', $request->to)->endOfDay();
            $query->whereBetween('created_at', [$from, $to]);
        }

        $attendances = $query->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Attendance/Index', compact('attendances'));
    }

    /**
     * Store a new attendance record (face verification + GPS).
     */
    public function store(Request $request)
    {
        $request->validate([
            'image'       => 'required|string',
            'type'        => 'required|in:check_in,check_out,proof_of_delivery',
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'delivery_id' => 'nullable|exists:deliveries,id',
        ]);

        $user = auth()->user();
        $faceService = app(\App\Services\FaceRecognizeService::class);

        // FILE HANDLING: Strip out data URI first before doing anything
        $imageBase64 = $request->image;
        if (strpos($imageBase64, ',') !== false) {
            @list(, $imageBase64) = explode(',', $imageBase64);
        }
        
        $imageBytes = base64_decode($imageBase64);
        if (!$imageBytes) {
             return response()->json([
                 'status'  => 'error',
                 'message' => 'Invalid image encoding.'
             ], 400);
        }

        try {
            // Gunakan $imageBase64 yang sudah di-strip prefix-nya ke Face Service
            $verificationResult = $faceService->verifyFace($user, $imageBase64);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
        
        if (!$verificationResult['success']) {
             return response()->json([
                 'status'  => 'error',
                 'message' => $verificationResult['message'] ?? 'Face verification failed'
             ], 400);
        }

        // 5km Radius Check for Proof of Delivery
        if ($request->type === 'proof_of_delivery' && $request->delivery_id) {
            $delivery = Delivery::find($request->delivery_id);
            if ($delivery) {
                $distanceToTarget = $this->haversineDistance(
                    (float)$request->latitude, (float)$request->longitude,
                    (float)$delivery->destination_lat, (float)$delivery->destination_lng
                );

                if ($distanceToTarget > 5) { // 5km limit
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Distance mismatch! You are ' . round($distanceToTarget, 2) . 'km away from the destination. Access denied for PoD verification.'
                    ], 403);
                }
            }
        }

        $isMatch = $verificationResult['is_match'];
        $similarityScore = $verificationResult['similarity_score'];

        $fileName = 'attendances/' . $user->id . '_' . time() . '_' . Str::random(10) . '.jpg';
        Storage::disk('public')->put($fileName, $imageBytes);

        $address = $this->getAddress($request->latitude, $request->longitude);

        // Create record in attendances
        $attendance = Attendance::create([
            'user_id'               => $user->id,
            'delivery_id'           => $request->delivery_id,
            'type'                  => $request->type,
            'photo_path'            => $fileName,
            'face_similarity_score' => $similarityScore,
            'validation_status'     => $isMatch ? 'valid' : 'invalid',
            'latitude'              => $request->latitude,
            'longitude'             => $request->longitude,
            'address'               => $address,
        ]);

        // Create record in tracking_logs
        TrackingLog::create([
            'driver_id'   => $user->id,
            'delivery_id' => $request->delivery_id,
            'latitude'    => $request->latitude,
            'longitude'   => $request->longitude,
        ]);

        return response()->json([
            'status'            => 'success',
            'message'           => 'Attendance recorded successfully.',
            'is_match'          => $isMatch,
            'similarity_score'  => $similarityScore,
            'validation_status' => $attendance->validation_status
        ], 200);
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
     * Test face recognition without persisting any data.
     * Used for research/evaluation: capture & verify, but do NOT create
     * attendance or tracking records.
     */
    public function testFace(Request $request)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $user = auth()->user();
        $faceService = app(\App\Services\FaceRecognizeService::class);

        // Strip data URI prefix if present
        $imageBase64 = $request->image;
        if (strpos($imageBase64, ',') !== false) {
            @list(, $imageBase64) = explode(',', $imageBase64);
        }

        $imageBytes = base64_decode($imageBase64);
        if (!$imageBytes) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid image encoding.',
            ], 400);
        }

        try {
            $verificationResult = $faceService->verifyFace($user, $imageBase64);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }

        if (!$verificationResult['success']) {
            return response()->json([
                'status'           => 'error',
                'message'          => $verificationResult['message'] ?? 'Face verification failed',
                'similarity_score' => $verificationResult['similarity_score'] ?? 0,
            ], 200);
        }

        return response()->json([
            'status'           => 'success',
            'message'          => 'Test verification completed. No data saved.',
            'is_match'         => $verificationResult['is_match'],
            'similarity_score' => $verificationResult['similarity_score'],
        ], 200);
    }

    /**
     * Reset test data for the currently authenticated driver.
     * Deletes all attendance and tracking_logs records belonging to the user.
     * Intended for research/testing — do NOT expose to admin role for production.
     */
    public function resetTestData(Request $request)
    {
        $user = auth()->user();

        // Only drivers can reset their own data
        if ($user->role !== 'driver') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only drivers can reset their test data.',
            ], 403);
        }

        try {
            // Collect photo paths to delete from storage
            $photoPaths = Attendance::where('user_id', $user->id)
                ->whereNotNull('photo_path')
                ->pluck('photo_path');

            foreach ($photoPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            $attendanceCount = Attendance::where('user_id', $user->id)->delete();
            $trackingCount = TrackingLog::where('driver_id', $user->id)->delete();

            return response()->json([
                'status'           => 'success',
                'message'          => "Reset complete. Deleted {$attendanceCount} attendance records and {$trackingCount} tracking logs.",
                'attendance_count' => $attendanceCount,
                'tracking_count'   => $trackingCount,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Reset test data error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reset test data: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Haversine distance formula to calculate distance between two points on Earth.
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
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
}