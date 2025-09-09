<?php
/**
 * TPT Government Platform - Middleware Interface
 *
 * Standard interface for all middleware classes.
 */

namespace Core\Middleware;

interface MiddlewareInterface
{
    /**
     * Handle the middleware
     *
     * @param mixed $request Request object
     * @param mixed $response Response object
     * @param callable $next Next middleware in chain
     * @return mixed
     */
    public function handle($request, $response, callable $next);
}
