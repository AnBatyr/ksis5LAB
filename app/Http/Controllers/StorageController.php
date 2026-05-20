<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    private string $disk = 'local';

    public function handleGetOrHead(Request $request, string $path = ''): JsonResponse|Response
    {
        $path = $this->normalizePath($path);

        if (!Storage::disk($this->disk)->exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($request->isMethod('HEAD')) {
            return $this->headResponse($path);
        }

        return $this->getResponse($path);
    }

    /**
     * Ответ для GET запросов
     */
    private function getResponse(string $path): JsonResponse|Response
    {
        if (Storage::disk($this->disk)->directoryExists($path)) {
            $items = [];
            $files = Storage::disk($this->disk)->files($path);
            $directories = Storage::disk($this->disk)->directories($path);

            foreach ($directories as $dir) {
                $items[] = [
                    'name' => basename($dir),
                    'type' => 'directory',
                    'size' => 0,
                    'modified' => date('Y-m-d H:i:s', Storage::disk($this->disk)->lastModified($dir)),
                ];
            }

            foreach ($files as $file) {
                $items[] = [
                    'name' => basename($file),
                    'type' => 'file',
                    'size' => Storage::disk($this->disk)->size($file),
                    'modified' => date('Y-m-d H:i:s', Storage::disk($this->disk)->lastModified($file)),
                ];
            }

            return response()->json([
                'path' => '/' . ltrim($path, '/'),
                'items' => $items,
            ]);
        }

        // Файл - возвращаем содержимое
        $fileContent = Storage::disk($this->disk)->get($path);
        $mimeType = Storage::disk($this->disk)->mimeType($path) ?? 'application/octet-stream';

        return response($fileContent, 200, [
            'Content-Type' => $mimeType,
        ]);
    }

    private function headResponse(string $path): Response
    {
        $headers = [];

        if (Storage::disk($this->disk)->directoryExists($path)) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            $headers['X-Content-Type'] = 'directory';
            $headers['Content-Length'] = '0';
            $modified = Storage::disk($this->disk)->lastModified($path);
            $headers['Last-Modified'] = date('D, d M Y H:i:s T', $modified);
        } else {
            $headers['Content-Length'] = (string) Storage::disk($this->disk)->size($path);
            $modified = Storage::disk($this->disk)->lastModified($path);
            $headers['Last-Modified'] = date('D, d M Y H:i:s T', $modified);
            $mimeType = Storage::disk($this->disk)->mimeType($path);
            $headers['Content-Type'] = $mimeType ?? 'application/octet-stream';
        }

        return response('', 200, $headers);
    }

    /**
     * Загрузка файла в хранилище с перезаписью (PUT)
     * Дополнительное задание: копирование файла через X-Copy-From
     */
    public function put(Request $request, string $path = ''): JsonResponse
    {
        $path = $this->normalizePath($path);

        // Проверка на копирование файла
        $copyFrom = $request->header('X-Copy-From');
        if ($copyFrom !== null) {
            $sourcePath = $this->normalizePath($copyFrom);

            if (!Storage::disk($this->disk)->exists($sourcePath)) {
                return response()->json(['error' => 'Source file not found'], 404);
            }

            if (Storage::disk($this->disk)->directoryExists($sourcePath)) {
                return response()->json(['error' => 'Cannot copy directory with X-Copy-From'], 400);
            }

            // Копируем файл
            Storage::disk($this->disk)->copy($sourcePath, $path);

            return response()->json(['message' => 'File copied successfully'], 201);
        }

        // Обычная загрузка файла
        $content = $request->getContent();

        if (empty($content)) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Создаем директорию если нужно
        $directory = dirname($path);
        if ($directory !== '.' && $directory !== '') {
            Storage::disk($this->disk)->makeDirectory($directory);
        }

        // Сохраняем файл с перезаписью
        Storage::disk($this->disk)->put($path, $content);

        return response()->json(['message' => 'File uploaded successfully'], 201);
    }

    /**
     * Удаление файла или каталога (DELETE)
     */
    public function delete(Request $request, string $path = ''): JsonResponse
    {
        $path = $this->normalizePath($path);

        if (!Storage::disk($this->disk)->exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Проверяем, что не удаляем корневую директорию
        if ($path === '' || $path === '/') {
            return response()->json(['error' => 'Cannot delete root directory'], 400);
        }

        if (Storage::disk($this->disk)->directoryExists($path)) {
            Storage::disk($this->disk)->deleteDirectory($path);
        } else {
            Storage::disk($this->disk)->delete($path);
        }

        return response()->json(['message' => 'Deleted successfully'], 204);
    }

    /**
     * Информация об API
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'service' => 'File Storage API',
            'methods' => [
                'PUT' => 'Upload file (with X-Copy-From header for copy)',
                'GET' => 'Get file or directory listing',
                'HEAD' => 'Get file metadata (size, modification date)',
                'DELETE' => 'Delete file or directory',
            ],
        ]);
    }

    /**
     * Нормализация пути
     */
    private function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');
        $path = str_replace('..', '', $path);
        return $path;
    }
}
