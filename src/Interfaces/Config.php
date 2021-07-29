<?php declare(strict_types=1);

namespace PhpCli\Interfaces;

interface Config
{
    public function get(string $name);

    public function getData(): \stdClass;

    public function has(string $name): bool;

    public function set(string $name, $value): self;

    public function setData(\stdClass $data): self;
}