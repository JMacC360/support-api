<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NextcloudService
{
    private string $baseUrl;
    private string $storageUrl;
    private string $username;
    private string $password;
    private string $folder;

    public function __construct()
    {
        $this->baseUrl    = rtrim(config('services.nextcloud.base_url'), '/');
        $this->storageUrl = rtrim(config('services.nextcloud.storage_url'), '/');
        $this->username   = config('services.nextcloud.username');
        $this->password   = config('services.nextcloud.password');
        $this->folder     = config('services.nextcloud.folder');
    }

    /**
     * Upload a file to Nextcloud.
     *
     * @param  UploadedFile|string  $file     UploadedFile instance or absolute local path
     * @param  string|null          $filename Destination filename (defaults to original name)
     * @param  string|null          $subfolder Optional subfolder inside the configured folder
     * @return array{success: bool, url: string|null, path: string|null, message: string}
     */
    public function upload(UploadedFile|string $file, ?string $filename = null, ?string $subfolder = null): array
    {
        try {
            $isUploadedFile = $file instanceof UploadedFile;
            $filename       = $filename ?? ($isUploadedFile ? $file->getClientOriginalName() : basename($file));
            $mimeType       = $isUploadedFile ? $file->getMimeType() : mime_content_type($file);
            $contents       = $isUploadedFile ? $file->get() : file_get_contents($file);

            $remotePath = $this->buildRemotePath($filename, $subfolder);
            $uploadUrl  = "{$this->baseUrl}/{$remotePath}";

            // Ensure the target folder exists before uploading
            $this->ensureFolder($subfolder);

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withBody($contents, $mimeType)
                ->put($uploadUrl);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'url'     => $this->getPublicUrl($remotePath),
                    'path'    => $remotePath,
                    'message' => 'File uploaded successfully.',
                ];
            }

            Log::error('Nextcloud upload failed', [
                'status'  => $response->status(),
                'url'     => $uploadUrl,
                'body'    => $response->body(),
            ]);

            return [
                'success' => false,
                'url'     => null,
                'path'    => null,
                'message' => "Upload failed with status {$response->status()}.",
            ];
        } catch (\Throwable $e) {
            Log::error('Nextcloud upload exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'url'     => null,
                'path'    => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update (overwrite) an existing file on Nextcloud.
     *
     * The file is PUT to the exact $remotePath provided, replacing whatever is there.
     * If the file does not yet exist it will be created (same as upload).
     *
     * @param  UploadedFile|string $file       New file contents
     * @param  string              $remotePath Full remote path to overwrite (e.g. "ess-franchise-uploads/clients/doc.pdf")
     * @return array{success: bool, url: string|null, path: string|null, message: string}
     */
    public function update(UploadedFile|string $file, string $remotePath): array
    {
        try {
            $isUploadedFile = $file instanceof UploadedFile;
            $mimeType       = $isUploadedFile ? $file->getMimeType() : mime_content_type($file);
            $contents       = $isUploadedFile ? $file->get() : file_get_contents($file);

            $uploadUrl = "{$this->baseUrl}/{$remotePath}";

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withBody($contents, $mimeType)
                ->put($uploadUrl);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'url'     => $this->getPublicUrl($remotePath),
                    'path'    => $remotePath,
                    'message' => 'File updated successfully.',
                ];
            }

            Log::error('Nextcloud update failed', [
                'status'  => $response->status(),
                'url'     => $uploadUrl,
                'body'    => $response->body(),
            ]);

            return [
                'success' => false,
                'url'     => null,
                'path'    => null,
                'message' => "Update failed with status {$response->status()}.",
            ];
        } catch (\Throwable $e) {
            Log::error('Nextcloud update exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'url'     => null,
                'path'    => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a file from Nextcloud.
     *
     * @param  string $remotePath Path relative to the storage URL (e.g. "folder/file.pdf")
     */
    public function delete(string $remotePath): bool
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->delete("{$this->baseUrl}/{$remotePath}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Nextcloud delete exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check whether a file or folder exists on Nextcloud.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->send('PROPFIND', "{$this->baseUrl}/{$remotePath}");

            return $response->status() === 207;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Create a folder (MKCOL) on Nextcloud, including nested folders.
     */
    public function makeFolder(string $folderPath): bool
    {
        $parts       = explode('/', trim($folderPath, '/'));
        $accumulated = '';

        foreach ($parts as $part) {
            $accumulated .= '/' . $part;
            $url          = "{$this->baseUrl}{$accumulated}";

            if (! $this->exists(ltrim($accumulated, '/'))) {
                $response = Http::withBasicAuth($this->username, $this->password)
                    ->send('MKCOL', $url);

                if (! in_array($response->status(), [201, 405])) {
                    Log::error('Nextcloud MKCOL failed', ['status' => $response->status(), 'url' => $url]);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * List files and folders inside a remote path.
     *
     * @param  string|null $remotePath Path relative to the storage URL. Defaults to the configured folder.
     * @return array{success: bool, items: array|null, message: string}
     */
    public function listFiles(?string $remotePath = null): array
    {
        $remotePath = $remotePath ?? $this->folder;
        $url        = "{$this->baseUrl}/{$remotePath}";

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $url);

            if ($response->status() !== 207) {
                Log::error('Nextcloud PROPFIND failed', [
                    'status' => $response->status(),
                    'url'    => $url,
                ]);

                return [
                    'success' => false,
                    'items'   => null,
                    'message' => "Failed to list files (status {$response->status()}).",
                ];
            }

            $items = $this->parsePropfindResponse($response->body(), $remotePath);

            return [
                'success' => true,
                'items'   => $items,
                'message' => 'Files retrieved successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('Nextcloud listFiles exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'items'   => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse a WebDAV PROPFIND XML response into a plain array.
     * The first entry (the requested directory itself) is skipped.
     */
    private function parsePropfindResponse(string $xml, string $requestedPath): array
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('d', 'DAV:');

        $responses     = $xpath->query('//d:response');
        $encodedFolder = rawurlencode(trim($requestedPath, '/'));
        $items         = [];

        foreach ($responses as $index => $node) {
            $href = trim($xpath->evaluate('string(d:href)', $node));

            // Skip the first entry — it is the requested directory itself
            if ($index === 0) {
                continue;
            }

            $displayName   = $xpath->evaluate('string(d:propstat/d:prop/d:displayname)', $node);
            $contentLength = $xpath->evaluate('string(d:propstat/d:prop/d:getcontentlength)', $node);
            $contentType   = $xpath->evaluate('string(d:propstat/d:prop/d:getcontenttype)', $node);
            $lastModified  = $xpath->evaluate('string(d:propstat/d:prop/d:getlastmodified)', $node);
            $etag          = $xpath->evaluate('string(d:propstat/d:prop/d:getetag)', $node);
            $isCollection  = $xpath->query('d:propstat/d:prop/d:resourcetype/d:collection', $node)->length > 0;

            $filename   = $displayName ?: basename(rtrim($href, '/'));
            $remotePath = ltrim(parse_url($href, PHP_URL_PATH), '/');

            $items[] = [
                'name'          => $filename,
                'path'          => $remotePath,
                'url'           => "{$this->baseUrl}{$href}",
                'type'          => $isCollection ? 'folder' : 'file',
                'size'          => $contentLength !== '' ? (int) $contentLength : null,
                'mime_type'     => $contentType ?: null,
                'last_modified' => $lastModified ?: null,
                'etag'          => $etag ?: null,
            ];
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildRemotePath(string $filename, ?string $subfolder): string
    {
        $parts = array_filter([$this->folder, $subfolder, $filename]);

        return implode('/', $parts);
    }

    private function ensureFolder(?string $subfolder): void
    {
        $folder = $subfolder
            ? "{$this->folder}/{$subfolder}"
            : $this->folder;

        $this->makeFolder($folder);
    }

    private function getPublicUrl(string $remotePath): string
    {
        return "{$this->baseUrl}/remote.php/dav/files/{$this->username}/{$remotePath}";
    }
}
