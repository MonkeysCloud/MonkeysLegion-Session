<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Middleware;

use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The session manager instance.
     *
     * @var SessionManager
     */
    protected SessionManager $manager;

    /**
     * Create a new middleware instance.
     *
     * @param SessionManager $manager
     */
    public function __construct(SessionManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            $this->isReading($request) ||
            $this->tokensMatch($request)
        ) {
            return $handler->handle($request);
        }

        throw new RuntimeException('CSRF token mismatch.');
    }

    /**
     * Determine if the HTTP request uses a read-only method.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isReading(ServerRequestInterface $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function tokensMatch(ServerRequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);

        return is_string($token) &&
               is_string($this->manager->token()) &&
               hash_equals($this->manager->token(), $token);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $parsedBody = $request->getParsedBody();
        
        $token = is_array($parsedBody) ? ($parsedBody['_token'] ?? null) : null;

        if (!$token) {
            $token = $request->getHeaderLine('X-CSRF-TOKEN');
        }

        if (!$token) {
            $token = $request->getHeaderLine('X-XSRF-TOKEN');
        }

        return $token;
    }
}
