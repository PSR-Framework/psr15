<?php

declare(strict_types=1);

namespace Tests\Furious\Psr15;

use Furious\Psr15\Exception\MiddlewareAlreadyCalledException;
use Furious\Psr15\Next;
use Furious\Psr7\Response;
use Furious\Psr7\ServerRequest;
use Furious\Psr7\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

class NextTest extends TestCase
{
    private SplQueue $queue;
    private RequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->queue = new SplQueue();
        $this->request = new ServerRequest('GET', '/');
        $this->handler = $this->createFallbackHandler();
    }

    public function testSuccess(): void
    {
        $next = new Next($this->queue, $this->handler);
        $this->assertInstanceOf(RequestHandlerInterface::class, $next);
    }

    public function testCloneQueue(): void
    {
        $next = new Next($this->queue, $this->handler);
        $this->assertAttributeNotSame($this->queue, 'queue', $next);
        $this->assertAttributeEquals($this->queue, 'queue', $next);
    }

    public function testHandler(): void 
    {
        $next = new Next($this->queue, $this->handler);
        $this->assertAttributeSame($this->handler, 'handler', $next);
    }

    public function testMiddlewareCallNext(): void 
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo'));
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->withMethod('GET');

        $firstMiddleware = new class($request) implements MiddlewareInterface
        {
            private ServerRequest $request;

            public function __construct(ServerRequest $request)
            {
                $this->request = $request;
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($this->request);
            }
        };

        $secondMiddleware = new class($cannedRequest) implements MiddlewareInterface
        {
            private ServerRequest $request;

            public function __construct(ServerRequest $request)
            {
                $this->request = $request;
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
            {
                Assert::assertEquals($this->request->getMethod(), $request->getMethod());
                return new Response();
            }
        };

        $this->queue->enqueue($firstMiddleware);
        $this->queue->enqueue($secondMiddleware);

        $next = new Next($this->queue, $this->handler);
        $response = $next->handle($request);
        $this->assertNotSame($this->response, $response);
    }

    public function testNextDelegatesToHandler(): void
    {
        $expectedResponse = $this
            ->prophesize(ResponseInterface::class)
            ->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $handler
            ->handle($this->request)
            ->willReturn($expectedResponse)
            ->shouldBeCalled()
        ;

        $next = new Next($this->queue, $handler->reveal());
        $this->assertSame($expectedResponse, $next->handle($this->request));
    }

    public function testEnqueuedMiddleware(): void 
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::any())
            ->shouldNotBeCalled()
        ;

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process($this->request, Argument::type(Next::class))
            ->willReturn($response);

        $this->queue->enqueue($middleware->reveal());

        $next = new Next($this->queue, $handler->reveal());

        $this->assertSame($response, $next->handle($this->request));
    }

    public function testMiddlewareReturnResponse(): void 
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::any())
            ->shouldNotBeCalled()
        ;

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $firstRoute = $this->prophesize(MiddlewareInterface::class);
        $firstRoute
            ->process($this->request, Argument::type(Next::class))
            ->willReturn($response)
        ;
        $this->queue->enqueue($firstRoute->reveal());

        $secondRoute = $this->prophesize(MiddlewareInterface::class);
        $secondRoute
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        
        $this->queue->enqueue($secondRoute->reveal());

        $next = new Next($this->queue, $handler->reveal());

        $this->assertSame($response, $next->handle($this->request));
    }

    public function testSecondInvocationAttempt(): void
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::any())
            ->willReturn(new Response());

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function (array $args): ResponseInterface {
                return $args[1]->handle($args[0]);
            })
            ->shouldBeCalledTimes(1);

        $this->queue->push($middleware->reveal());

        $next = new Next($this->queue, $handler->reveal());
        $next->handle($this->request);

        $this->expectException(MiddlewareAlreadyCalledException::class);
        $next->handle($this->request);
    }

    public function createFallbackHandler(ResponseInterface $response = null) : RequestHandlerInterface
    {
        $response = $response ?: $this->createDefaultResponse();

        return new class ($response) implements RequestHandlerInterface
        {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request) : ResponseInterface
            {
                return $this->response;
            }
        };
    }

    public function createDefaultResponse(): ResponseInterface
    {
        $this->response = $this->prophesize(ResponseInterface::class);
        return $this->response->reveal();
    }
}