<?php

declare(strict_types=1);

namespace Tests\Furious\Psr15;

use Furious\Psr15\Exception\EmptyPipelineException;
use Furious\Psr15\MiddlewarePipe;
use Furious\Psr15\PipeInterface;
use Furious\Psr7\Request;
use Furious\Psr7\Response;
use Furious\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

class MiddlewarePipeTest extends TestCase
{
    private Request $request;
    private MiddlewarePipe $pipeline;

    protected function setUp(): void
    {
        $this->request  = new ServerRequest('GET', 'http://example.com/', '1.1', [], [], 'php://memory');
        $this->pipeline = new MiddlewarePipe();
    }

    public function testCreateHandler(): RequestHandlerInterface
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn(new Response());

        /** @var RequestHandlerInterface $request */
        $request = $handler->reveal();

        return $request;
    }

    public function testInteropMiddleware(): void
    {
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $pipeline = new MiddlewarePipe();
        /** @var MiddlewareInterface $middleware */
        $middleware = $middleware->reveal();
        $pipeline->pipe($middleware);

        $this->assertSame($response, $pipeline->process($this->request, $handler));
    }

    public function testProcessInvokes(): void
    {
        $this->pipeline->pipe(new class() implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
            {
                $response = $handler->handle($request);
                $response->getBody()->write("First body\n");

                return $response;
            }
        });

        $this->pipeline->pipe(new class() implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                $response->getBody()->write("Second body\n");
                return $response;
            }
        });

        $response = new Response();
        $response->getBody()->write("Third body\n");
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($response));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new ServerRequest('GET', 'http://example.com/', '1.1', [], [], 'php://memory');
        $response = $this->pipeline->process($request, $this->testCreateHandler());
        $body = (string) $response->getBody();
        
        $this->assertContains('First body', $body);
        $this->assertContains('Second body', $body);
        $this->assertContains('Third body', $body);
    }

    public function testInvokesHandler(): void
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());

        $request = new ServerRequest('GET', 'http://example.com/', '1.1', [], [], 'php://memory');

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($expected);

        $result = $this->pipeline->process($request, $handler->reveal());

        $this->assertSame($expected, $result);
    }

    public function testReturnResponse(): void
    {
        $return = new Response();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($return));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new ServerRequest('GET', 'http://example.com/', '1.1', [], [], 'php://memory');
        $result  = $this->pipeline->process($request, $this->testCreateHandler());
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], true));
    }

    public function testHandleRaiseException(): void
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $this->expectException(EmptyPipelineException::class);
        $this->expectExceptionMessage('No middleware available to process the request');

        $this->pipeline->handle($request);
    }

    public function testHandleProcessesEnqueuedMiddleware(): void
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $firstMiddleware = $this->prophesize(MiddlewareInterface::class);
        $firstMiddleware
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $handler = $args[1];
                return $handler->handle($request);
            });

        $secondMiddleware = $this->prophesize(MiddlewareInterface::class);
        $secondMiddleware
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($firstMiddleware->reveal());
        $pipeline->pipe($secondMiddleware->reveal());

        $this->assertSame($response, $pipeline->handle($this->request));
    }

    private function getNotCalledMiddleware() : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->shouldNotBeCalled();

        return $middleware->reveal();
    }

    private function getPassToHandlerMiddleware() : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->will(function (array $args) {
                return $args[1]->handle($args[0]);
            })
            ->shouldBeCalledTimes(1);

        return $middleware->reveal();
    }

    private function getMiddlewareWhichReturnsResponse(ResponseInterface $response) : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->willReturn($response)
            ->shouldBeCalledTimes(1);

        return $middleware->reveal();
    }
}