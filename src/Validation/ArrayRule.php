<?php declare(strict_types=1);

namespace PhpCli\Validation;

class ArrayRule extends RegexRule
{
    protected string $name = 'array';

    protected array $delimiters = [',', '|'];

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must an array.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        if (is_string($input) && strlen($input) > 1) {
            foreach ($this->delimiters as $delim) {
                if (false !== strpos($input, $delim)) {
                    return true;
                }
            }

            return $input[0] === '[' && $input[-1] === ']';
        }

        return is_array($input);
    }
}