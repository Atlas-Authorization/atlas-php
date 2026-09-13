<?php

declare(strict_types=1);

namespace Atlas\Exception;

use Atlas\AtlasException;
use Atlas\ErrorItem;

/**
 * Raised on any non-2xx response from the Backend API.
 *
 * Carries the HTTP {@see AtlasApiException::getStatus()} and the full parsed
 * {@see AtlasApiException::getErrors()} envelope. Branch on {@see getCode()}
 * (the first error's stable code) or use {@see hasCode()}.
 *
 * {@see fromResponse()} selects the most specific subclass for the status, so a
 * caller can `catch (NotFoundException)` or fall back to `catch
 * (AtlasApiException)`.
 */
class AtlasApiException extends AtlasException
{
    /**
     * @param list<ErrorItem> $errors
     */
    public function __construct(
        private readonly int $status,
        private readonly array $errors,
        ?string $message = null,
    ) {
        $first = $errors[0] ?? null;
        parent::__construct(
            $message
                ?? ($first?->message ?: null)
                ?? "Atlas API request failed with status {$status}",
            $status,
        );
    }

    /** HTTP status of the failed response. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * The full §9.1 error envelope, in order.
     *
     * @return list<ErrorItem>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** The first error's stable code, the field callers branch on most. */
    public function getCode(): ?string
    {
        return $this->errors[0]->code ?? null;
    }

    /** True when any error in the envelope carries the given stable code. */
    public function hasCode(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Construct the most specific subclass for an HTTP status + parsed errors.
     *
     * @param list<ErrorItem> $errors
     */
    public static function fromResponse(int $status, array $errors): self
    {
        $class = match (true) {
            $status === 400, $status === 422 => BadRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($status, $errors);
    }
}
