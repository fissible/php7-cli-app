<?php declare(strict_types=1);

namespace PhpCli\Validation;

class RegexRule extends Rule
{
    protected string $name = 'regex';

    protected string $regex;

    public function __construct(string $regex = '')
    {
        $this->setRegex($regex);
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" field format is invalid.';
    }

    /**
     * @param string $regex
     * @return self
     */
    public function setRegex(string $regex): self
    {
        $this->regex = $regex;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        if (strlen($input) < 1) {
            return true;
        }
        if ($this->regex) {
            $result = preg_match($this->regex, $input);
            if ($result === false) {
                throw new \Exception('RegexRule regular expression error: '.preg_last_error_msg());
            }
            return (bool) $result;
        }
        return false;
    }
}