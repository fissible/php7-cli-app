<?php declare(strict_types=1);

namespace PhpCli;

class Route {

    private string $name;

    private array $aliases;

    private $callable;

    private $Command;

    public function __construct(string $name, $action)
    {
        $this->setName($name);
        $this->setAction($action);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAction()
    {
        if (isset($this->callable)) {
            return $this->callable;
        }

        return $this->Command;
    }

    public function getAliases(): array
    {
        return $this->aliases ?? [];
    }

    public function is(string $name)
    {
        if ($this->name === $name) {
            return true;
        }
        if (isset($this->aliases)) {
            return in_array($name, $this->aliases);
        }
        return false;
    }

    public function run(Application $Application, array $params = [])
    {
        if (isset($this->callable)) {
            $function = $this->callable;
            return $function($Application, $params);
        }

        if (isset($this->Command)) {
            return $this->Command->run($params);
        }
    }

    protected function setAction($action)
    {
        if (is_callable($action)) {
            $this->callable = $action;
            return $this;
        }
        
        if ($action instanceof Command) {
            $this->Command = $action;
            return $this;
        }

        throw new \InvalidArgumentException('Action must be callable or instance of Command.');
    }

    protected function setName(string $name)
    {
        if (false !== strpos($name, ',')) {
            $parts = explode(',', $name);
            $name = array_shift($parts);
            $this->aliases = $parts;
        } elseif (false !== strpos($name, '|')) {
            $parts = explode('|', $name);
            $name = array_shift($parts);
            $this->aliases = $parts;
        }

        $this->name = $name;

        return $this;
    }
}