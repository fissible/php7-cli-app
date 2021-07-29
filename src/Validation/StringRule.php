<?php declare(strict_types=1);

namespace PhpCli\Validation;

class StringRule extends RegexRule
{
    protected string $name = 'string';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must be a string.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return is_string($input);
    }
}