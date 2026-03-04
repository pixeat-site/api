<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Redimensiona e comprime imagens para análise por IA (Gemini/Groq).
 * Garante base64 dentro do limite do Groq (~3MB) e reduz payload para o Gemini.
 * Saída sempre JPEG para melhor compressão.
 */
class AnalysisImageService
{
    /** Limite Groq: base64 até ~4MB no request; usamos 3MB com margem. */
    public const MAX_BASE64_LENGTH = 3 * 1024 * 1024;

    private const MAX_DIMENSION = 1280;
    private const JPEG_QUALITY_DEFAULT = 82;
    private const JPEG_QUALITY_MIN = 65;

    /**
     * Normaliza a imagem para envio às IAs: redimensiona e comprime em JPEG.
     * Retorna ['base64' => string, 'mime_type' => 'image/jpeg'].
     *
     * @return array{base64: string, mime_type: string}
     * @throws \Exception
     */
    public function normalize(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $mimeType = $file->getMimeType();

        $raw = file_get_contents($path);
        $base64 = base64_encode($raw);
        if (strlen($base64) <= self::MAX_BASE64_LENGTH) {
            return ['base64' => $base64, 'mime_type' => $mimeType];
        }

        Log::info('📐 [AnalysisImage] Comprimindo imagem para análise', [
            'original_base64_chars' => strlen($base64),
            'limit' => self::MAX_BASE64_LENGTH,
        ]);

        return $this->resizeAndEncode($path, $mimeType);
    }

    /**
     * @return array{base64: string, mime_type: string}
     * @throws \Exception
     */
    private function resizeAndEncode(string $path, string $mimeType): array
    {
        $source = $this->createImageFromMime($path, $mimeType);
        if ($source === false) {
            throw new \Exception('Não foi possível processar a imagem');
        }

        $w = \imagesx($source);
        $h = \imagesy($source);
        $quality = self::JPEG_QUALITY_DEFAULT;
        $maxDim = self::MAX_DIMENSION;

        while (true) {
            [$nw, $nh] = $this->scaleDimensions($w, $h, $maxDim);
            $out = \imagecreatetruecolor($nw, $nh);
            if ($out === false) {
                \imagedestroy($source);
                throw new \Exception('Falha ao criar imagem redimensionada');
            }
            \imagecopyresampled($out, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            \imagejpeg($out, null, $quality);
            $jpegBytes = ob_get_clean();
            \imagedestroy($out);

            $base64 = base64_encode($jpegBytes);
            if (strlen($base64) <= self::MAX_BASE64_LENGTH) {
                \imagedestroy($source);
                Log::info('📐 [AnalysisImage] Imagem comprimida', [
                    'base64_chars' => strlen($base64),
                    'dimensions' => "{$nw}x{$nh}",
                    'quality' => $quality,
                ]);
                return ['base64' => $base64, 'mime_type' => 'image/jpeg'];
            }

            if ($maxDim <= 512 && $quality <= self::JPEG_QUALITY_MIN) {
                \imagedestroy($source);
                return ['base64' => $base64, 'mime_type' => 'image/jpeg'];
            }
            $maxDim = max(512, (int) ($maxDim * 0.75));
            $quality = max(self::JPEG_QUALITY_MIN, $quality - 8);
        }
    }

    /**
     * @return \GdImage|resource|false
     */
    private function createImageFromMime(string $path, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => \imagecreatefromjpeg($path),
            'image/png' => \imagecreatefrompng($path),
            'image/gif' => \imagecreatefromgif($path),
            default => false,
        };
    }

    private function scaleDimensions(int $w, int $h, int $maxDimension): array
    {
        if ($w <= $maxDimension && $h <= $maxDimension) {
            return [$w, $h];
        }
        $ratio = $maxDimension / max($w, $h);
        return [(int) round($w * $ratio), (int) round($h * $ratio)];
    }
}
