<?php

namespace App\Services\Pixelforge;

use RuntimeException;

/**
 * Any non-2xx answer (or no answer) from Pixelforge. Branch on $errorCode, as the
 * docs say; $status is null when the request never got a response.
 */
class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly ?int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfter = null,
        public readonly ?string $param = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Worth retrying later: rate/concurrency limits, overload, 5xx, network errors.
     * Not: daily quota (resets at UTC midnight), validation, content policy, auth.
     */
    public function isRetryable(): bool
    {
        if ($this->status === null) {
            return true;
        }

        return match ($this->errorCode) {
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded' => true,
            default => $this->status >= 500,
        };
    }

    /** Message safe to show to the user. */
    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy. Nothing was charged.',
            'daily_limit_exceeded' => 'Pixelforge\'s daily generation quota is used up. It resets at midnight UTC. Nothing was charged.',
            'validation_failed' => 'Pixelforge did not accept these settings: '.$this->getMessage(),
            'invalid_api_key' => 'Image generation is not configured right now. Nothing was charged.',
            'network_error' => 'Could not reach Pixelforge. Nothing was charged.',
            default => 'Pixelforge could not take this request. Nothing was charged.',
        };
    }
}
