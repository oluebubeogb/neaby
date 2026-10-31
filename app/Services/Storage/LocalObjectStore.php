<?php

namespace App\Services\Storage;

use App\Contracts\ObjectStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Laravel-disk backed store. With NEABY_UPLOAD_DISK=public this writes to
 * storage/app/public on the VPS (persist it with a Coolify volume) and serves
 * from /storage/... via the storage:link symlink.
 */
class LocalObjectStore implements ObjectStore
{
    public function __construct(private readonly string $diskName = 'public') {}

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName);
    }

    public function putFile(UploadedFile $file, string $directory): string
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $key = trim($directory, '/').'/'.now()->format('Y/m').'/'.Str::ulid()->toBase32().'.'.$ext;
        $this->disk()->putFileAs(dirname($key), $file, basename($key), ['visibility' => 'public']);

        return $key;
    }

    public function put(string $key, string $contents, ?string $contentType = null): string
    {
        $this->disk()->put($key, $contents, ['visibility' => 'public']);

        return $key;
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($key);
    }

    public function delete(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function url(?string $key): ?string
    {
        if (! $key) {
            return null;
        }
        if (Str::startsWith($key, ['http://', 'https://', '/'])) {
            return $key;
        }
        if ($this->diskName === 'public') {
            // Relative URL: works behind any proxy/domain without APP_URL drift.
            return '/storage/'.ltrim($key, '/');
        }

        return $this->disk()->url($key);
    }
}
