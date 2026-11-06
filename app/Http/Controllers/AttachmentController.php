<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class AttachmentController extends Controller
{
    public function proxy(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'url' => ['required', 'string'],
        ]);

        $targetUrl = $validated['url'];
        if (! $this->isAllowedAttachmentUrl($targetUrl)) {
            return response()->json(['message' => 'Invalid attachment URL.'], 403);
        }

        $response = Http::withBasicAuth(
            (string) config('services.nextcloud.username'),
            (string) config('services.nextcloud.password')
        )->get($targetUrl);

        if (! $response->successful()) {
            return response()->json(['message' => 'Unable to load attachment.'], $response->status());
        }

        $contentType = $response->header('Content-Type') ?: 'application/octet-stream';
        $filename = basename(parse_url($targetUrl, PHP_URL_PATH) ?: 'attachment');

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function isAllowedAttachmentUrl(string $url): bool
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return false;
        }

        $storageUrl = rtrim((string) config('services.nextcloud.storage_url'), '/');
        $baseUrl = rtrim((string) config('services.nextcloud.base_url'), '/');
        $username = rawurlencode((string) config('services.nextcloud.username'));
        $davFromBase = $baseUrl !== '' ? "{$baseUrl}/remote.php/dav/files/{$username}" : '';

        $allowedPrefixes = array_filter([$storageUrl, $davFromBase]);
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($url, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
