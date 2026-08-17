<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\ScholarUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Utilities
{
    public static function uploadImage(ScholarUser $user, $file)
    {
        try {
            $randomName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $directoryPath = "users/{$user->id}/images";
            Storage::makeDirectory($directoryPath, 0755, true);
            $path = $file->storeAs($directoryPath, $randomName);
            $url = Storage::url($path);

            return [
                'status' => 200,
                'message' => 'Image uploaded successfully.',
                'path' => $url
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Failed to upload image.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload a file to a given storage subdirectory and return the stored path.
     * The path is relative (e.g. "products/images/abc.jpg") — prepend APP_URL
     * or the CDN domain when serving to the client.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $directory  e.g. "products/images"
     * @return array{status:int, path?:string, message:string}
     */
    public static function uploadFile($file, string $directory): array
    {
        try {
            $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $name, 'public');

            return [
                'status'  => 200,
                'message' => 'File uploaded successfully.',
                'path'    => $path,   // storage-relative path — resolve URL with asset(Storage::url($path))
            ];
        } catch (\Exception $e) {
            Log::error('File upload failed', ['directory' => $directory, 'error' => $e->getMessage()]);
            return [
                'status'  => 500,
                'message' => 'Failed to upload file.',
                'error'   => $e->getMessage(),
            ];
        }
    }

    public static function deleteImage(string $imagePath)
    {
        if (!is_string($imagePath) || empty($imagePath)) {
            return;
        }

        $relativePath = str_replace('/storage/', '', $imagePath);

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    public static function generateInitialsImage(string $initials, \Illuminate\Database\Eloquent\Model $user): string
    {
        $userId = $user->id;
        $imageSize = 200;
        $textColorOptions = [
            [255, 0, 0],
            [0, 255, 0],
            [0, 0, 255],
            [255, 165, 0],
            [128, 0, 128],
            [0, 128, 128],
            [255, 192, 203]
        ];

        if (!extension_loaded('gd')) {
            Log::error('PHP GD extension is not loaded. Cannot generate initials image.');
            return 'Error: GD extension not available.';
        }

        $image = @imagecreatetruecolor($imageSize, $imageSize);
        if (!$image) {
            Log::error('Failed to create image');
            return 'Error: Failed to create image.';
        }

        $randomColor = $textColorOptions[array_rand($textColorOptions)];
        $backgroundColor = imagecolorallocate($image, $randomColor[0], $randomColor[1], $randomColor[2]);
        imagefill($image, 0, 0, $backgroundColor);

        $textColor = imagecolorallocate($image, 255, 255, 255);

        $font = 5;

        $textWidth = imagefontwidth($font) * strlen($initials);
        $textHeight = imagefontheight($font);

        $x = ($imageSize - $textWidth) / 2;
        $y = ($imageSize - $textHeight) / 2;

        if (!imagestring($image, $font, (int)$x, (int)$y, $initials, $textColor)) {
            Log::error('Failed to add text to the image');
            imagedestroy($image);
            return 'Error: Failed to add text to the image.';
        }

        $directoryPath = "public/users/{$userId}/images";
        try {
            Storage::makeDirectory($directoryPath, 0755, true);
        } catch (\Exception $e) {
            Log::error("Failed to create directory: {$directoryPath}, Error: " . $e->getMessage());
            imagedestroy($image);
            return 'Error: Failed to create directory.';
        }

        $filename = time() . '_' . uniqid() . '.png';
        $path = "{$directoryPath}/{$filename}";

        ob_start();
        if (!imagepng($image)) {
            Log::error('Failed to output image as PNG');
            ob_end_clean();
            imagedestroy($image);
            return 'Error: Failed to output image.';
        }
        $imageData = ob_get_clean();

        try {
            Storage::put($path, $imageData);
        } catch (\Exception $e) {
            Log::error("Failed to store image at {$path}, Error: " . $e->getMessage());
            imagedestroy($image);
            return 'Error: Failed to store image.';
        }

        imagedestroy($image);

        return Storage::url($path);
    }
}
