<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FaceRecognizeService
{
    protected $host;
    protected $port;

    public function __construct()
    {
        $this->host = env("PYTHON_SERVICE");
        $this->port = env("PYTHON_SERVICE_PORT");
    }

    public function registerUserFace(User $user, string $path): bool
    {
        if (!Storage::exists($path)) {
            Log::error("File not found at storage: " . $path);
            return false;
        }

        try {
            $fullPath = Storage::path($path);
            $photo = fopen($fullPath, 'r');
            $response = Http::attach('file', $photo, 'face.jpg')
                ->post("http://{$this->host}:{$this->port}/registration");
            
            if (is_resource($photo)) fclose($photo);

            if ($response->successful() && $response->json('success')) {
                $user->update(['face_embedding' => $response->json('embedding')]);
                Cache::forget('all_users_list');
                return true;
            }

            return false;
        } catch (\Exception $err) {
            Log::error("Registration error: " . $err->getMessage());
            return false;
        } finally {
            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

    public function verifyFace(User $user, string $liveImageBase64): array
    {
        if (empty($user->face_image_path)) {
            throw new \Exception("Reference face image not found for this driver in database.");
        }

        $referenceImageBytes = null;
        $disk = Storage::disk('public');
        
        // Cek apakah file ada di storage/app/public
        if ($disk->exists($user->face_image_path)) {
            $referenceImageBytes = $disk->get($user->face_image_path);
        } 
        // Fallback: Cek apakah file ada langsung di folder public/ (misal public/avatars/...)
        elseif (file_exists(public_path($user->face_image_path))) {
            $referenceImageBytes = file_get_contents(public_path($user->face_image_path));
        } 
        else {
            throw new \Exception("Reference face image file is missing on the server. Path requested: " . $user->face_image_path);
        }

        $referenceImageBase64 = base64_encode($referenceImageBytes);

        // Retry up to 2 times for transient connection issues
        $maxAttempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->connectTimeout(10)
                    ->post("http://{$this->host}:{$this->port}/api/verify", [
                        'reference_image_base64' => $referenceImageBase64,
                        'live_image_base64'      => $liveImageBase64,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success'          => true,
                        'similarity_score' => $data['similarity_score'] ?? 0.0,
                        'is_match'         => $data['is_match'] ?? false,
                    ];
                }

                // Got a response but not successful (4xx/5xx) — return immediately, no retry
                $detail = $response->json('detail');
                $statusCode = $response->status();
                
                Log::warning("Face verify failed (HTTP {$statusCode}): " . ($detail ?? 'No detail'));

                // Provide friendly messages for common cases
                $message = $detail ?? 'An error occurred during verification.';
                if (stripos($message, 'No face detected in live image') !== false) {
                    $message = 'No face detected in your photo. Please ensure your face is clearly visible and well-lit, then try again.';
                } elseif (stripos($message, 'No face detected in reference') !== false) {
                    $message = 'Reference photo issue. Please contact admin to update your profile photo.';
                } elseif (stripos($message, 'Invalid base64') !== false) {
                    $message = 'Invalid image data. Please try capturing again.';
                }

                return [
                    'success' => false,
                    'message' => $message,
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $err) {
                $lastError = $err;
                Log::warning("Face verify connection attempt {$attempt}/{$maxAttempts} failed: " . $err->getMessage());

                if ($attempt < $maxAttempts) {
                    // Wait briefly before retrying
                    sleep(1);
                    continue;
                }
            } catch (\Exception $err) {
                Log::error("Face verify error: " . $err->getMessage());
                return [
                    'success' => false,
                    'message' => 'Verification service error: ' . $err->getMessage(),
                ];
            }
        }

        // All retries exhausted
        Log::error("Face verify failed after {$maxAttempts} attempts: " . ($lastError ? $lastError->getMessage() : 'unknown'));
        return [
            'success' => false,
            'message' => 'Face recognition service is unreachable. The service may be starting up — please wait a moment and try again.',
        ];
    }
}