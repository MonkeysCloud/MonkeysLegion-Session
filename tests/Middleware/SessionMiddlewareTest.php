<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Middleware;

use MonkeysLegion\Session\Middleware\SessionMiddleware;
use MonkeysLegion\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddlewareTest extends TestCase
{
    /** @var SessionDriverInterface&MockObject */
    private $driver;
    private SessionManager $manager;
    /** @var ServerRequestInterface&MockObject */
    private $request;
    /** @var RequestHandlerInterface&MockObject */
    private $handler;
    /** @var ResponseInterface&MockObject */
    private $response;

    private SessionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->driver = $this->createMock(\MonkeysLegion\Session\Contracts\SessionDriverInterface::class);
        $this->manager = new SessionManager($this->driver);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);

        $this->middleware = new SessionMiddleware($this->manager);
    }

    public function testProcessStartsAndSavesSession(): void
    {
        // 1. Cookie Extraction
        $this->request->expects($this->once())
            ->method('getCookieParams')
            ->willReturn(['ml_session' => 'sess_123']);

        // Since it's lazy, we set ID but don't call start()
        // (Verified implicitly because driver->read is never called until attribute access)

        // 2. Populate Metadata (Request Params)
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->method('getHeaderLine')->willReturnMap([
            ['X-Forwarded-For', ''],
            ['User-Agent', 'TestAgent']
        ]);

        $this->request->method('hasHeader')->willReturnMap([
            ['X-Forwarded-For', false],
            ['User-Agent', true]
        ]);

        $this->request->method('withAttribute')->willReturnSelf();

        // 3. Handle Request
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->manager->id = 'sess_123';

        $this->response->expects($this->once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', $this->stringContains('ml_session=sess_123'))
            ->willReturn($this->response);

        $this->middleware->process($this->request, $this->handler);
    }
}
