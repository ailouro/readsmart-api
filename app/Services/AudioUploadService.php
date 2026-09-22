<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class AudioUploadService
{
    public function uploadMispronunciationAudio(UploadedFile $file): ?string
    {
        try {
            $upload = cloudinary()->upload($file->getRealPath(), [
                'folder' => 'mispronunciations',
                'resource_type' => 'video', // Audio is uploaded under 'video' resource type
            ]);
            return $upload->getSecurePath();
        } catch (\Exception $e) {
            Log::error('Mispronunciation audio upload failed: ' . $e->getMessage());
            return null;
        }
    }
}