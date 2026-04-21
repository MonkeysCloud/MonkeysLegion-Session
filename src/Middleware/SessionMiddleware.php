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
        private readonly SessionManager $manager,
        array $config = []
    ) {
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
