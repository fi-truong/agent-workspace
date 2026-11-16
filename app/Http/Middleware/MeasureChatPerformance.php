<?php

namespace App\Http\Middleware;

use App\Services\ChatPerformance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class MeasureChatPerformance
{
    public function __construct(private readonly ChatPerformance $performance) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->performance->start();
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = $exception instanceof ValidationException ? $exception->status
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            $this->performance->finish($status);
            throw $exception;
        }

        if ($response instanceof StreamedResponse && ($callback = $response->getCallback()) !== null) {
            $response->setCallback(function () use ($callback, $response): void {
                try {
                    $callback();
                } catch (Throwable $exception) {
                    $this->performance->fail();
                    throw $exception;
                } finally {
                    $this->performance->finish($response->getStatusCode());
                }
            });
        } else {
            $this->performance->finish($response->getStatusCode());
        }

        return $response;
    }
}
