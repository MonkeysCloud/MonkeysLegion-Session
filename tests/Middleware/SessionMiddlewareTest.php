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
    public function testProcessStartsAndSavesSession(): void
    {
        /** @var SessionManager&MockObject $manager */
        $manager = $this->createMock(SessionManager::class);
        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->createMock(ServerRequestInterface::class);
        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        /** @var ResponseInterface&MockObject $response */
        $response = $this->createMock(ResponseInterface::class);

        $middleware = new SessionMiddleware($manager);

        // 1. Cookie Extraction
        $request->method('withAttribute')->willReturnSelf();
        $request->expects($this->once())
            ->method('getCookieParams')
            ->willReturn(['ml_session' => 'sess_123']);

        // 2. Start Session
        $manager->expects($this->once())
            ->method('start')
            ->with('sess_123');

        // 3. Populate Metadata (Request Params)
        // Use atLeastOnce() to avoid any() deprecation and provide expectations
        $request->expects($this->atLeastOnce())->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $request->expects($this->atLeastOnce())->method('getHeaderLine')->willReturnMap([
            ['X-Forwarded-For', ''],
            ['User-Agent', 'TestAgent']
        ]);

        $request->expects($this->atLeastOnce())->method('hasHeader')->with('User-Agent')->willReturn(true);

        $manager->expects($this->once())->method('setIpAddress')->with('127.0.0.1');
        $manager->expects($this->once())->method('setUserAgent')->with('TestAgent');

        // 4. Handle Request
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        // 5. Save Session
        $manager->expects($this->once())->method('save');

        // 6. Cookie Setting
        $manager->expects($this->atLeastOnce())->method('isStarted')->willReturn(true);
        $manager->expects($this->atLeastOnce())->method('getId')->willReturn('sess_123');

        $response->expects($this->once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', $this->stringContains('ml_session=sess_123'))
            ->willReturn($response);

        $middleware->process($request, $handler);
    }
}
