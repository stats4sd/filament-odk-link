<?php

namespace Stats4sd\FilamentOdkLink\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Thrown when ODK Central returns an unexpected (non-200) response that is not
 * an XLSForm validation problem — e.g. an expired session, a rate limit, or a
 * server-side error.
 */
class OdkCentralRequestException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $odkMessage = null,
        public readonly ?string $requestUrl = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response, string $action, ?string $requestUrl = null): self
    {
        $odkMessage = $response->json('message');

        if (! is_string($odkMessage)) {
            $odkMessage = null;
        }

        $message = "ODK Central returned HTTP {$response->status()} while {$action}."
            .' This is not an XLSForm file validation issue.'
            .($odkMessage !== null ? " ODK Central said: {$odkMessage}" : '');

        return new self($message, $response->status(), $odkMessage, $requestUrl);
    }

    /**
     * Transient conditions worth another attempt: a stale/expired session (401),
     * timeouts, throttling, and any server-side error.
     */
    public function isRetryable(): bool
    {
        if (in_array($this->status, [401, 408, 429], true)) {
            return true;
        }

        return $this->status >= 500;
    }
}
