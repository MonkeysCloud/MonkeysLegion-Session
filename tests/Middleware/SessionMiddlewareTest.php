<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Middleware;

use MonkeysLegion\Session\Middleware\SessionMiddleware;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddlewareTest extends TestCase
{
    /** @var SessionManager&MockObject */
    private $manager;
    /** @var ServerRequestInterface&MockObject */
    private $request;
    /** @var RequestHandlerInterface&MockObject */
    private $handler;
    /** @var ResponseInterface&MockObject */
    private $response;

    private SessionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(SessionManager::class);
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

        // 2. Start Session
        $this->manager->expects($this->once())
            ->method('start')
            ->with('sess_123');

        // 3. Populate Metadata (Request Params)
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->method('getHeaderLine')->willReturnMap([
            ['X-Forwarded-For', ''],
            ['User-Agent', 'TestAgent']
        ]);

        $this->request->method('hasHeader')->with('User-Agent')->willReturn(true);

        $this->manager->expects($this->once())->method('setIpAddress')->with('127.0.0.1');
        $this->manager->expects($this->once())->method('setUserAgent')->with('TestAgent');

        // 4. Handle Request
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        // 5. Save Session
        $this->manager->expects($this->once())->method('save');

        // 6. Cookie Setting
        $this->manager->method('isStarted')->willReturn(true);
        $this->manager->method('getId')->willReturn('sess_123');

        $this->response->expects($this->once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', $this->stringContains('ml_session=sess_123'))
            ->willReturn($this->response);

        $this->middleware->process($this->request, $this->handler);
    }
}
