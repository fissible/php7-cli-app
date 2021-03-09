<?php declare(strict_types=1);

namespace PhpCli;

class Router {

    protected Application $Application;

    protected Collection $Routes;

    public function __construct(Application $Application)
    {
        $this->Application = $Application;
        $this->Routes = new Collection();
    }

    public function bind(Route $Route)
    {
        $this->Routes->set($Route->getName(), $Route);

        return $this;
    }

    public function getRoutes(): Collection
    {
        return $this->Routes;
    }

    public function getRoute(string $name): ?Route
    {
        return $this->Routes->first(function (Route $Route) use ($name) {
            return $Route->is($name);
        });
    }

    public function has(string $name): bool
    {
        $Route = $this->getRoute($name);

        return !is_null($Route);
    }

    public function route(string $name)
    {
        $Route = $this->getRoute($name);

        $this->validate($Route);

        return $Route->run($this->Application);
    }

    public function validate($route)
    {
        if ($route instanceof Route && $this->Routes->contains($route)) {
            return null;
        }

        if (is_string($route)) {
            if ($this->has($route)) {
                return null;
            }

            throw new \RuntimeException(sprintf('Route "%s" not found.', $route));
        }

        throw new \RuntimeException('Route not found.');
    }
}