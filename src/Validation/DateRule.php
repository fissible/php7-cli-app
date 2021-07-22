<?php declare(strict_types=1);

namespace PhpCli\Validation;

class DateRule extends RegexRule
{
    protected string $name = 'date';

    protected string $format;

    public function __construct(string $format = '')
    {
        parent::__construct($format);
        $this->format = $format;
    }

    /**
     * @param string $regex
     * @return self
     */
    public function setRegex(string $regex): self
    {
        $regex = '/^'.str_replace(['/', '-'], ['\/', '\-'], $regex).'$/';

        // day
        if (false !== strpos($regex, 'd')) {
            $regex = str_replace('d', '(\d{2})', $regex);
        } elseif (false !== strpos($regex, 'D')) {
            $regex = str_replace('D', '([a-zA-Z]{3})', $regex);
        } elseif (false !== strpos($regex, 'j')) {
            $regex = str_replace('j', '(\d{1,2})', $regex);
        }

        // month
        if (false !== strpos($regex, 'm')) {
            $regex = str_replace('m', '(\d{2})', $regex);
        } elseif (false !== strpos($regex, 'M')) {
            $regex = str_replace('M', '([a-zA-Z]{3})', $regex);
        } elseif (false !== strpos($regex, 'n')) {
            $regex = str_replace('n', '(\d{1,2})', $regex);
        }

        // year
        if (false !== strpos($regex, 'y')) {
            $regex = str_replace('y', '(\d{2})', $regex);
        } elseif (false !== strpos($regex, 'Y')) {
            $regex = str_replace('Y', '(\d{4})', $regex);
        }

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
        if ($passes = parent::passes($name, $input)) {
            $date = \DateTime::createFromFormat($this->format, (string) $input);
            $passes = $date && $date->format($this->format) === $input;
        }

        return $passes;
    }
}