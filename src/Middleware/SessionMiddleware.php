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

    /**
     * @var list<string> Path prefixes or regex patterns to skip session start
     */
    private array $except;

    /** Whether sessions are enabled globally */
    private bool $enabled;

    public function __construct(
        private readonly SessionManager $manager,
        array $config = []
    ) {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->except = $config['except'] ?? [];
        unset($config['enabled'], $config['except']);

        $this->config = array_merge([
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
        // Sessions globally disabled — pass through
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        // Skip session entirely for exempt paths (e.g. sendBeacon endpoints)
        if ($this->isExempt($request)) {
            return $handler->handle($request);
        }

        // 1. Get Session ID from Cookie
        $cookies = $request->getCookieParams();
        $id = $cookies[$this->manager->getName()] ?? null;

        // 2. Set Context and Start
        $this->manager->start($id ?? '');

        $this->populateMetadata($request);

        // 3. Bind Manager to Request
        $request = $request->withAttribute('session', $this->manager);

        // 4. Handle Request
        try {
            $response = $handler->handle($request);
        } finally {
            // 5. Save Session (Only occurs if started!)
            $this->manager->save();
        }

        // 6. Add Cookie to Response
        if ($this->manager->isStarted() || $this->manager->getId() !== '') {
            $response = $this->addCookieToResponse($response, $this->manager->getId());
        }

        return $response;
    }

    /**
     * Check if the current request path matches any exempt pattern.
     *
     * Supports:
     *  - Plain prefix strings: "/api/" matches any path starting with /api/
     *  - Regex patterns (surrounded by #...#): "#^/admin/content/\d+/lock/release$#"
     */
    private function isExempt(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        foreach ($this->except as $pattern) {
            // Regex pattern (delimited with #)
            if (str_starts_with($pattern, '#') && preg_match($pattern, $path)) {
                return true;
            }

            // Plain prefix match
            if (str_starts_with($path, $pattern) || $path === $pattern) {
                return true;
            }
        }

        return false;
    }

    private function populateMetadata(ServerRequestInterface $request): void
    {
        $serverParams = $request->getServerParams();

        $ip = $serverParams['REMOTE_ADDR'] ?? null;
        if ($headerIp = $request->getHeaderLine('X-Forwarded-For')) {
             $ip = trim(explode(',', $headerIp)[0]);
        }

        $ua = $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null;

        $this->manager->setIpAddress($ip);
        $this->manager->setUserAgent($ua);
    }

    private function addCookieToResponse(ResponseInterface $response, string $sessionId): ResponseInterface
    {
        $cookieValue = sprintf(
            '%s=%s; Path=%s; Max-Age=%d; %s%sSameSite=%s',
            $this->manager->getName(),
            $sessionId,
            $this->config['cookie_path'],
            $this->config['cookie_lifetime'],
            $this->config['cookie_secure'] ? 'Secure; ' : '',
            $this->config['cookie_httponly'] ? 'HttpOnly; ' : '',
            $this->config['cookie_samesite']
        );
        
        if (!empty($this->config['cookie_domain'])) {
            $cookieValue .= '; Domain=' . $this->config['cookie_domain'];
        }

        return $response->withAddedHeader('Set-Cookie', $cookieValue);
    }
}
