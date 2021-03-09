<?php declare(strict_types=1);

namespace PhpCli;

class Argument {

    private string $name;

    private $required;

    private $value;

    public function __construct(string $name, ?bool $requiredValue = null, $defaultValue = null)
    {
        $this->name = $name;
        $this->required = $requiredValue;
        $this->value = $defaultValue;
    }

    public function empty(): bool
    {
        return $this->value === null;
    }

    public function equals($value): bool
    {
        return $this->value === $value;
    }

    public function is($name)
    {
        return $this->name === $name;
    }

    public function isRequired()
    {
        return $this->required === true;
    }

    public function isOptional()
    {
        return $this->required === false;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        if (!$this->isRequired() && $value === false) {
            $value = true;
        }
        $this->value = $value;

        return $this;
    }

    public function __toString()
    {
        return $this->value;
    }
}