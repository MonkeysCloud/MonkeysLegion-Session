<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Middleware;

use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(
        private SessionManager $manager,
        array $config = []
    ) {
        $this->config = array_merge([
            'cookie_name' => 'ml_session',
            'cookie_lifetime' => 7200, // 2 hours
            'cookie_path' => '/',
            'cookie_domain' => '',
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ], $config);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Get Session ID from Cookie
        $cookies = $request->getCookieParams();
        $id = $cookies[$this->config['cookie_name']] ?? null;

        // 2. Start Session (Locks & Reads)
        $this->manager->start($id);

        // 3. Populate Metadata
        $this->populateMetadata($request);

        // 4. Handle Request
        try {
            $response = $handler->handle($request);
        } finally {
            // 5. Save Session (Writes & Unlocks)
            $this->manager->save();
        }

        // 6. Add Cookie to Response
        if ($this->manager->isStarted() || $this->manager->getId()) {
            $response = $this->addCookieToResponse($response, $this->manager->getId());
        }

        return $response;
    }

    private function populateMetadata(ServerRequestInterface $request): void
    {
        $serverParams = $request->getServerParams();

        // IP Address
        $ip = $serverParams['REMOTE_ADDR'] ?? null;
        if ($headerIp = $request->getHeaderLine('X-Forwarded-For')) {
             // Simple extraction, take first IP
             $ip = trim(explode(',', $headerIp)[0]);
        }
        $this->manager->setIpAddress($ip);

        // User Agent
        if ($request->hasHeader('User-Agent')) {
            $this->manager->setUserAgent($request->getHeaderLine('User-Agent'));
        }
    }

    private function addCookieToResponse(ResponseInterface $response, string $sessionId): ResponseInterface
    {
        $cookieValue = sprintf(
            '%s=%s; Path=%s; Max-Age=%d; %s%sSameSite=%s',
            $this->config['cookie_name'],
            $sessionId,
            $this->config['cookie_path'],
            $this->config['cookie_lifetime'],
            $this->config['cookie_secure'] ? 'Secure; ' : '',
            $this->config['cookie_httponly'] ? 'HttpOnly; ' : '',
            $this->config['cookie_samesite']
        );
        
        if ($this->config['cookie_domain']) {
            $cookieValue .= '; Domain=' . $this->config['cookie_domain'];
        }

        return $response->withAddedHeader('Set-Cookie', $cookieValue);
    }
}
