<?php declare(strict_types=1);

namespace PhpCli\Validation;

class IntegerRule extends RegexRule
{
    protected string $name = 'int';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must an integer.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_INT) !== false;
    }
}