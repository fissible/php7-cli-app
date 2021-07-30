<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class Author {

    private $name;

    private $email;

    public function __construct(string $name, ?string $email = null)
    {
        $this->name = $name;
        $this->email = $email ?? null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function __toString(): string
    {
        $author = $this->name;

        if ($this->email) {
            $author .= ' <'.$this->email.'>';
        }

        return $author;
    }

    public static function parse(string $author): Author
    {
        $name = trim(Str::before($author, '<'));
        $email = Str::capture($author, '<', '>') ?: null;

        return new static($name, $email);
    }
}