<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    /**
     * Download de APK com suporte a resumable downloads
     */
    public function downloadApk(Request $request, string $filename)
    {
        // Log da tentativa de download
        Log::info("📱 [DOWNLOAD] Tentativa de download: {$filename}", [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'range' => $request->header('Range'),
        ]);

        // Validar nome do arquivo
        if (!preg_match('/^pixeat-v\d+\.\d+\.\d+\.apk$/', $filename)) {
            Log::warning("❌ [DOWNLOAD] Nome de arquivo inválido: {$filename}");
            abort(404, 'Arquivo não encontrado');
        }
        
        $filePath = public_path("downloads/{$filename}");
        
        if (!file_exists($filePath)) {
            Log::error("❌ [DOWNLOAD] Arquivo não existe: {$filePath}");
            abort(404, 'Arquivo não encontrado');
        }
        
        $fileSize = filesize($filePath);
        $mimeType = 'application/vnd.android.package-archive';
        
        Log::info("📋 [DOWNLOAD] Arquivo encontrado", [
            'file' => $filename,
            'size' => $fileSize,
            'size_mb' => round($fileSize / 1024 / 1024, 2) . 'MB'
        ]);
        
        // Headers base para download
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400', // 24h cache
            'Pragma' => 'public',
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 86400),
            'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', filemtime($filePath)),
            'ETag' => '"' . md5_file($filePath) . '"',
        ];
        
        // Verificar se é um range request (download resumível)
        $rangeHeader = $request->header('Range');
        
        if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            return $this->handleRangeRequest($filePath, $fileSize, $matches, $headers, $filename);
        }
        
        // Download completo normal
        $headers['Content-Length'] = $fileSize;
        
        Log::info("✅ [DOWNLOAD] Enviando arquivo completo: {$filename}");
        
        return response()->file($filePath, $headers);
    }
    
    /**
     * Lidar com range requests (download resumível)
     */
    private function handleRangeRequest(string $filePath, int $fileSize, array $matches, array $headers, string $filename)
    {
        $start = (int) $matches[1];
        $end = $matches[2] ? (int) $matches[2] : $fileSize - 1;
        
        // Validar range
        if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
            Log::warning("❌ [DOWNLOAD] Range inválido para {$filename}", [
                'start' => $start,
                'end' => $end,
                'fileSize' => $fileSize
            ]);
            
            return response('', 416, [
                'Content-Range' => "bytes */{$fileSize}"
            ]);
        }
        
        $contentLength = $end - $start + 1;
        
        Log::info("📦 [DOWNLOAD] Range request para {$filename}", [
            'start' => $start,
            'end' => $end,
            'content_length' => $contentLength,
            'percentage' => round(($end / $fileSize) * 100, 1) . '%'
        ]);
        
        // Headers para range response
        $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        $headers['Content-Length'] = $contentLength;
        
        // Usar stream para arquivos grandes
        return response()->stream(
            function () use ($filePath, $start, $contentLength) {
                $handle = fopen($filePath, 'rb');
                if ($handle === false) {
                    Log::error("❌ [DOWNLOAD] Não foi possível abrir arquivo: {$filePath}");
                    return;
                }
                
                fseek($handle, $start);
                
                $chunkSize = 8192; // 8KB chunks
                $bytesRemaining = $contentLength;
                
                while ($bytesRemaining > 0 && !feof($handle)) {
                    $bytesToRead = min($chunkSize, $bytesRemaining);
                    $chunk = fread($handle, $bytesToRead);
                    
                    if ($chunk === false) {
                        break;
                    }
                    
                    echo $chunk;
                    flush();
                    
                    $bytesRemaining -= strlen($chunk);
                }
                
                fclose($handle);
                
                Log::info("✅ [DOWNLOAD] Range enviado com sucesso");
            },
            206, // Partial Content
            $headers
        );
    }
    
    /**
     * Informações sobre downloads disponíveis
     */
    public function info()
    {
        $downloadsPath = public_path('downloads');
        $files = [];
        
        if (is_dir($downloadsPath)) {
            $apkFiles = glob($downloadsPath . '/pixeat-v*.apk');
            
            foreach ($apkFiles as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $modified = filemtime($file);
                
                $files[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'modified' => date('Y-m-d H:i:s', $modified),
                    'download_url' => url("/downloads/{$filename}"),
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Informações de download',
            'data' => [
                'files' => $files,
                'total_files' => count($files),
                'server_info' => [
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_limit' => ini_get('memory_limit'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                ]
            ]
        ]);
    }
}
