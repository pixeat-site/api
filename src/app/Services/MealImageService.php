<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço de processamento de imagens de refeições (Story 5.1).
 * Gera thumbnail e persiste no storage público; retorna URL.
 */
class MealImageService
{
    private const MAX_WIDTH = 400;
    private const JPEG_QUALITY = 60;

    /**
     * Salva thumbnail comprimida da imagem e retorna a URL pública.
     *
     * @throws \Exception
     */
    public function saveThumbnail(UploadedFile $file, int $userId): string
    {
        $filename = 'meal_' . $userId . '_' . time() . '_' . uniqid() . '.jpg';
        $path = 'meals/' . $filename;

        $sourcePath = $file->getRealPath();
        $mimeType = \mime_content_type($sourcePath);

        /** @var \GdImage|resource|false $sourceImage */
        $sourceImage = $this->createImageFromMime($sourcePath, $mimeType);
        if ($sourceImage === false) {
            throw new \Exception('Não foi possível processar a imagem');
        }

        $originalWidth = \imagesx($sourceImage);
        $originalHeight = \imagesy($sourceImage);
        [$newWidth, $newHeight] = $this->calculateDimensions($originalWidth, $originalHeight);

        /** @var \GdImage|resource $thumbnail */
        $thumbnail = \imagecreatetruecolor($newWidth, $newHeight);
        \imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        $tempPath = sys_get_temp_dir() . '/' . $filename;
        \imagejpeg($thumbnail, $tempPath, self::JPEG_QUALITY);

        \imagedestroy($sourceImage);
        \imagedestroy($thumbnail);

        Storage::disk('public')->put($path, file_get_contents($tempPath));
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return url('storage/' . $path);
    }

    /**
     * @return \GdImage|resource|false
     */
    private function createImageFromMime(string $sourcePath, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg' => \imagecreatefromjpeg($sourcePath),
            'image/png' => \imagecreatefrompng($sourcePath),
            'image/gif' => \imagecreatefromgif($sourcePath),
            default => throw new \Exception('Tipo de imagem não suportado: ' . $mimeType),
        };
    }

    private function calculateDimensions(int $originalWidth, int $originalHeight): array
    {
        if ($originalWidth > self::MAX_WIDTH) {
            $ratio = self::MAX_WIDTH / $originalWidth;
            return [self::MAX_WIDTH, (int) ($originalHeight * $ratio)];
        }
        return [$originalWidth, $originalHeight];
    }
}
