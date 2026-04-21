<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * Upload image to Cloudinary or fallback to local storage
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string Image URL
     */
    public static function uploadImage(UploadedFile $file, string $folder = 'uploads')
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        try {
           $cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],
    ]);
            
            $uploadOptions = [
                'folder' => $folder,
                'resource_type' => 'image',
            ];

            $uploadResult = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );

            Log::info('Image uploaded to Cloudinary', [
                'file' => $file->getClientOriginalName(),
                'url' => $uploadResult['secure_url']
            ]);

            return $uploadResult['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed, fallback to local storage', [
                'error' => $e->getMessage()
            ]);

            // Fallback: lưu vào storage local
            $path = $file->store($folder, 'public');
            return $path;
        }
    }
}