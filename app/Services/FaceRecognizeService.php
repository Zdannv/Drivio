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
            throw new \Exception("Reference face image not found for this driver");
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($user->face_image_path)) {
            throw new \Exception("Reference face image file is missing on the server");
        }

        $referenceImageBytes = $disk->get($user->face_image_path);
        $referenceImageBase64 = base64_encode($referenceImageBytes);

        try {
            $response = Http::post("http://{$this->host}:{$this->port}/api/verify", [
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

            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'An error occurred during verification.'
            ];
        } catch (\Exception $err) {
            Log::error("Face verify error: " . $err->getMessage());
            return [
                'success' => false,
                'message' => 'Connection to face recognition service failed.'
            ];
        }
    }
}
