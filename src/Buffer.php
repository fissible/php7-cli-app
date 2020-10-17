<?php declare(strict_types=1);

namespace PhpCli;

class Buffer
{
    private array $buffer;

    public function __construct(array $buffer = [])
    {
        $this->buffer = $buffer;
    }

    public function clean()
    {
        $this->buffer = [];
    }

    public function collect(string $string): void
    {
        $this->buffer[] = $string;
    }

    public function print(string $string): void
    {
        $this->collect($string);
    }

    public function printf(string $format, ...$vars)
    {
        $this->collect(sprintf(rtrim($format), ...$vars));
    }

    public function printl(string $string): void
    {
        $this->collect(rtrim($string) . "\n");
    }

    public function printlf(string $format, ...$vars): void
    {
        $this->collect(sprintf(rtrim($format) . "\n", ...$vars));
    }

    public function flush(): array
    {
        $buffer = $this->get();
        $this->clean();

        return $buffer;
    }

    public function get(): array
    {
        return $this->buffer;
    }
}