<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Where user uploads live. Phase 1 = VPS disk (LocalObjectStore);
 * swapping to S3/R2/MinIO later is a new implementation + config change.
 */
interface ObjectStore
{
    /** Store an uploaded file under $directory and return its storage key. */
    public function putFile(UploadedFile $file, string $directory): string;

    /** Store raw contents at $key and return the key. */
    public function put(string $key, string $contents, ?string $contentType = null): string;

    public function exists(string $key): bool;

    public function delete(string $key): void;

    /** Public URL for a key (relative is fine for same-origin assets). */
    public function url(?string $key): ?string;
}
