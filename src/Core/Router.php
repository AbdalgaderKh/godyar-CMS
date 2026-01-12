<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{0:string,1:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        $this->routes[] = [$pattern, $handler];
        return $this;
    }

    /**
     * This project historically used a GET-only router. Some newer features
     * register POST routes; since dispatch() matches by path (not method),
     * we provide post() as a thin alias to keep compatibility.
     */
    public function post(string $pattern, callable $handler): self
    {
        return $this->get($pattern, $handler);
    }

    public function dispatch(string $path): bool
    {
        foreach ($this->routes as $route) {
            [$pattern, $handler] = $route;
            if (preg_match($pattern, $path, $matches)) {
                $handler($matches);
                return true;
            }
        }
        return false;
    }
}
