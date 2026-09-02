<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where a company logo actually lives is controlled by
 * config('filesystems.uploads_disk'):
 *  - 'public' (default, local dev) / 's3' (Cloudflare R2) both go through
 *    Laravel's Storage disk abstraction and save a relative path like
 *    "logos/xxx.png" — logo_url is rebuilt from that path on read.
 *  - 'cloudinary' bypasses Storage entirely (no Flysystem adapter needed)
 *    and saves the full secure_url Cloudinary returns — logo_url is just
 *    that stored value.
 * Both container disks on Render are wiped on every deploy/restart, so
 * production should use 's3' or 'cloudinary', never 'public'.
 */
class CompanyLogoStorage
{
    public function store(UploadedFile $file): string
    {
        if (config('filesystems.uploads_disk') === 'cloudinary') {
            $result = $this->cloudinary()->uploadApi()->upload($file->getRealPath(), [
                'public_id' => 'logos/' . Str::uuid(),
                'overwrite' => true,
            ]);

            return $result['secure_url'];
        }

        return $file->store('logos', config('filesystems.uploads_disk'));
    }

    public function delete(?string $logo): void
    {
        if (!$logo) {
            return;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            // Cloudinary (or any other URL-based) logo — recover the
            // public_id we chose at upload time from the URL itself:
            // .../upload/v<version>/logos/<uuid>.<ext> -> "logos/<uuid>".
            if (preg_match('#/upload/v\d+/(.+)\.\w+$#', $logo, $matches)) {
                $this->cloudinary()->uploadApi()->destroy($matches[1]);
            }
            return;
        }

        // A bare relative path only resolves through the local/S3 Storage
        // disk it was actually uploaded to. If uploads now go through
        // Cloudinary instead, this is a stale reference from before the
        // switch — 'cloudinary' isn't a registered Storage disk, and the
        // file itself is long gone anyway (Render's container disk doesn't
        // survive a deploy). Nothing to delete.
        if (str_contains($logo, 'logos/') && config('filesystems.uploads_disk') !== 'cloudinary') {
            Storage::disk(config('filesystems.uploads_disk'))->delete($logo);
        }
    }

    public function url(?string $logo): ?string
    {
        if (!$logo || !str_contains($logo, 'logos/')) {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        // Same stale-reference case as delete() above: a relative path
        // can't be served through Cloudinary, which isn't a Storage disk.
        if (config('filesystems.uploads_disk') === 'cloudinary') {
            return null;
        }

        return Storage::disk(config('filesystems.uploads_disk'))->url($logo);
    }

    private function cloudinary(): Cloudinary
    {
        return new Cloudinary(config('services.cloudinary.url'));
    }
}
