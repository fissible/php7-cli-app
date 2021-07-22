<?php declare(strict_types=1);

namespace PhpCli\Validation;

class RequiredRule extends Rule
{
    protected string $name = 'required';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" field is required.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        if (is_string($input)) {
            return strlen($input) > 0;
        }
        return !empty($input);
    }
}