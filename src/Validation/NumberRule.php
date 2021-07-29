<?php declare(strict_types=1);

namespace PhpCli\Validation;

class NumberRule extends RegexRule
{
    protected string $name = 'number';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must be numeric.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return is_numeric($input);
    }
}