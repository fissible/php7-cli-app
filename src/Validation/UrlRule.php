<?php declare(strict_types=1);

namespace PhpCli\Validation;

class UrlRule extends RegexRule
{
    protected string $name = 'url';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must a URL.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_URL) !== false;
    }
}