<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Middleware;

use MonkeysLegion\Session\Middleware\VerifyCsrfToken;
use MonkeysLegion\Session\SessionManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class VerifyCsrfTokenTest extends TestCase
{
    /** @var SessionManager&MockObject */
    private $manager;
    /** @var ServerRequestInterface&MockObject */
    private $request;
    /** @var RequestHandlerInterface&MockObject */
    private $handler;
    /** @var ResponseInterface */
    private $response;

    private VerifyCsrfToken $middleware;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(SessionManager::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createStub(ResponseInterface::class);

        $this->middleware = new VerifyCsrfToken($this->manager);
    }

    public function testSafeMethodsAreBypassed(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request = $this->createMock(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn($method);

            $this->handler->expects($this->atLeastOnce())
                ->method('handle')
                ->with($request)
                ->willReturn($this->response);

            $this->middleware->process($request, $this->handler);
        }
    }

    public function testTokensMatchFromPostData(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getParsedBody')->willReturn(['_token' => 'match_token']);
        $this->manager->method('token')->willReturn('match_token');

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->middleware->process($this->request, $this->handler);
    }

    public function testTokensMatchFromHeader(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getParsedBody')->willReturn([]);
        $this->request->method('getHeaderLine')->willReturnMap([
            ['X-CSRF-TOKEN', 'match_token'],
        ]);
        $this->manager->method('token')->willReturn('match_token');

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->middleware->process($this->request, $this->handler);
    }

    public function testTokensMismatchThrowsException(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getParsedBody')->willReturn(['_token' => 'wrong_token']);
        $this->manager->method('token')->willReturn('match_token');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSRF token mismatch.');

        $this->middleware->process($this->request, $this->handler);
    }

    public function testMissingTokenThrowsException(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getParsedBody')->willReturn([]);
        $this->manager->method('token')->willReturn('match_token');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSRF token mismatch.');

        $this->middleware->process($this->request, $this->handler);
    }
}
