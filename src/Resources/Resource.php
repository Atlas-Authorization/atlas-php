<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Base for every resource namespace: it holds the shared, config-bound
 * {@see Http} transport and turns a call into a single request naming a method,
 * a path, and its shapes. The surface mirrors the official TypeScript SDK
 * (`@atlas/backend`) namespace-for-namespace.
 */
abstract class Resource
{
    public function __construct(protected readonly Http $http)
    {
    }

    /**
     * The image content type for PNG / JPEG / GIF / WebP bytes, or null.
     */
    public static function sniffImageType(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    /**
     * Upload image bytes (a design background or logo) as the raw request body.
     * The content type is sniffed from the bytes when not given.
     *
     * @return array<string,mixed>
     */
    protected function uploadImage(string $path, string $bytes, ?string $kind, ?string $contentType): array
    {
        $type = $contentType ?? self::sniffImageType($bytes);
        if ($type === null) {
            throw new \InvalidArgumentException(
                'Could not tell the image type from its bytes; pass $contentType '
                . '(image/png, image/jpeg, image/gif or image/webp).',
            );
        }

        return $this->http->request('POST', $path, query: ['kind' => $kind], rawBody: $bytes, contentType: $type);
    }
}
