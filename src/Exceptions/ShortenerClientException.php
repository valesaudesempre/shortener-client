<?php

namespace ValeSaude\ShortenerClient\Exceptions;

use RuntimeException;

class ShortenerClientException extends RuntimeException
{
    public static function invalidURL(string $url): self
    {
        return new self(trans('valesaude::shortener_client.invalid_url', compact('url')));
    }

    public static function unexpectedResponse(): self
    {
        return new self(trans('valesaude::shortener_client.unexpected_response'));
    }

    public static function authenticationFailed(): self
    {
        return new self(trans('valesaude::shortener_client.authentication_failed'));
    }

    public static function apiError(string $message): self
    {
        return new self(trans('valesaude::shortener_client.api_error', compact('message')));
    }
}