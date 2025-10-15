<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;


class CloudinaryService
{
    private $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET')
            ]
        ]);
    }

    /**
     * Xóa ảnh từ Cloudinary dựa trên URL
     */
    public function deleteImageByUrl($imageUrl)
    {
        Log::info('Cloudinary aaaaa: ' . $imageUrl);
        if (empty($imageUrl) || !$this->isCloudinaryUrl($imageUrl)) {
            return false;
        }

        try {
            $publicId = $this->extractPublicIdFromUrl($imageUrl);
            if ($publicId) {
                $result = $this->cloudinary->uploadApi()->destroy($publicId);
                return $result['result'] === 'ok';
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary delete error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Kiểm tra URL có phải từ Cloudinary không
     */
    private function isCloudinaryUrl($url)
    {
        return strpos($url, 'cloudinary.com') !== false ||
            strpos($url, 'res.cloudinary.com') !== false;
    }

    /**
     * Trích xuất public_id từ Cloudinary URL
     */
    private function extractPublicIdFromUrl($url)
    {
        $parts = explode('/upload/', $url);
        if (count($parts) < 2) return null;
        $path = $parts[1];
        $path = preg_replace('/^v\d+\//', '', $path);
        $path = preg_replace('/\.\w+$/', '', $path);
        Log::info('Extracted public ID: ' . $path);
        return $path;
    }
}
