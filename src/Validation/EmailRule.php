<?php declare(strict_types=1);

namespace PhpCli\Validation;

class EmailRule extends RegexRule
{
    protected string $name = 'email';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must an email address.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
    }
}