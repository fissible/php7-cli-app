<?php declare(strict_types=1);

namespace PhpCli\Validation;

class FloatRule extends RegexRule
{
    protected string $name = 'float';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must a float.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
    }
}