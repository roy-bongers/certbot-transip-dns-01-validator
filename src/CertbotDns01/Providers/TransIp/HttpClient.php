<?php

namespace RoyBongers\CertbotDns01\Providers\TransIp;

use Transip\Api\Library\Exception\HttpRequest\UnauthorizedException;
use Transip\Api\Library\HttpClient\GuzzleClient;

class HttpClient extends GuzzleClient
{
    public function get(string $url, array $query = []): array
    {
        try {
            return parent::get($url, $query);
        } catch (UnauthorizedException $exception) {
            if (false === $this->isTokenRevoked($exception)) {
                throw $exception;
            }

            $this->clearToken();
            return parent::get($url, $query);
        }
    }

    public function post(string $url, array $body = []): void
    {
        try {
            parent::post($url, $body);
        } catch (UnauthorizedException $exception) {
            if (false === $this->isTokenRevoked($exception)) {
                throw $exception;
            }

            $this->clearToken();
            parent::post($url, $body);
        }
    }

    public function put(string $url, array $body): void
    {
        try {
            parent::put($url, $body);
        } catch (UnauthorizedException $exception) {
            if (false === $this->isTokenRevoked($exception)) {
                throw $exception;
            }

            $this->clearToken();
            parent::put($url, $body);
        }
    }

    public function patch(string $url, array $body): void
    {
        try {
            parent::patch($url, $body);
        } catch (UnauthorizedException $exception) {
            if (false === $this->isTokenRevoked($exception)) {
                throw $exception;
            }

            $this->clearToken();
            parent::patch($url, $body);
        }
    }

    public function delete(string $url, array $body = []): void
    {
        try {
            parent::delete($url, $body);
        } catch (UnauthorizedException $exception) {
            if (false === $this->isTokenRevoked($exception)) {
                throw $exception;
            }

            $this->clearToken();
            parent::delete($url, $body);
        }
    }

    protected function clearToken(): void
    {
        $this->clearCache();
        $this->setToken('');
        $this->checkAndRenewToken();
    }

    protected function isTokenRevoked(UnauthorizedException $exception): bool
    {
        return 'Your access token has been revoked.' !== $exception->getMessage();
    }
}
