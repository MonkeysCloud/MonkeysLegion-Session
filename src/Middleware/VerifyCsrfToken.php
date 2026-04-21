<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Middleware;

use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Middleware to verify CSRF tokens.
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected readonly SessionManager $manager)
    {
    }

    /**
     * Process an incoming server request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isReading($request) || $this->tokensMatch($request)) {
            return $handler->handle($request);
        }

        throw new RuntimeException('CSRF token mismatch.');
    }

    /**
     * Determine if the HTTP request uses a read-only method.
     */
    protected function isReading(ServerRequestInterface $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch(ServerRequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $this->manager->token();

        return is_string($token) &&
               $sessionToken !== '' &&
               hash_equals($sessionToken, $token);
    }

    /**
     * Get the CSRF token from the request.
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        /** @var mixed $parsedBody */
        $parsedBody = $request->getParsedBody();
        
        /** @var mixed $token */
        $token = is_array($parsedBody) ? ($parsedBody['_csrf'] ?? null) : null;

        if (!is_string($token)) {
            $token = $request->getHeaderLine('X-CSRF-TOKEN');
        }

        if ($token === '') {
            $token = $request->getHeaderLine('X-XSRF-TOKEN');
        }

        return is_string($token) && $token !== '' ? $token : null;
    }
}
