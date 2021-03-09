<?php declare(strict_types=1);

namespace PhpCli;

class Option
{
    private array $aliases;

    private string $name;

    private $description;

    private $value;

    private $requiresValue;

    private static $error_messages = [
        'ILLEGAL_OPTION' => 'Illegal option -%s',
        'ILLEGAL_NAME' => 'Option name "%s" already exists on command %s',
        'INVALID_NAME' => 'Option name must be a non-empty string'
    ];

    public function __construct(string $name, $requiresValue = null, ?string $description = null, $defaultValue = null)
    {
        $this->setName($name);
        $this->requiresValue = $requiresValue;
        $this->description = $description;
        $this->value = $defaultValue;
    }

    public function aliases(): array
    {
        return $this->aliases;
    }

    public function empty(): bool
    {
        return $this->value === null;
    }

    public function equals($value): bool
    {
        return $this->value === $value;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description ?? '';
    }

    public function value()
    {
        return $this->value;
    }

    public function is($name)
    {
        $name = trim($name, ':-');
        if ($this->name === $name) {
            return true;
        }
        return in_array($name, $this->aliases);
    }

    /**
     * Return true if this is a flag option
     * 
     * @param  string  $option The option name
     * @return boolean
     */
    public function isFlag()
    {
        return is_null($this->requiresValue);
    }

    public function isLong()
    {
        return ! $this->isShort();
    }

    public function isShort()
    {
        return strlen($this->name) == 1;
    }

    public function isRequired()
    {
        return !$this->isOptional() && !$this->isFlag();
    }

    public function isOptional()
    {
        return $this->requiresValue === false;
    }

    public function setAliases($aliases)
    {
        $this->aliases = (array) $aliases;
    }

    public function setName(string $name)
    {
        if (! is_string($name) || strlen($name) < 1) {
            throw new \InvalidArgumentException(static::$error_messages['INVALID_NAME']);
        }
        if (false !== strpos($name, '|')) {
            $name = explode('|', $name);
        }
        $aliases = array_map(function ($alias) {
            return trim($alias, ':-');
        }, (array) $name);

        $this->name = array_shift($aliases);
        $this->setAliases($aliases);

        return $this;
    }

    public function setValue($value)
    {
        if (!$this->isRequired() && $value === false) {
            $value = true;
        }
        $this->value = $value;

        return $this;
    }

    public function pushValue($value)
    {
        if (!isset($this->value)) {
            return $this->setValue($value);
        } elseif (!is_array($this->value)) {
            $this->value = (array) $this->value;
        }
        $this->value[] = $value;

        return $this;
    }

    public function getCliFlagsString(): string
    {
        $aliases = $this->aliases;
        array_unshift($aliases, $this->name);
        $name = implode(', ', array_map(function ($alias) {
            return (strlen($alias) === 1 ? '-' : '--') . $alias;
        }, $aliases));

        if ($this->requiresValue) {
            $name .= ' <' . (is_string($this->requiresValue) ? $this->requiresValue : 'value') . '>';
        }

        return $name;
    }

    public function getOptionString(?string $name = null)
    {
        return ($name ?? $this->name) . $this->getRequirementString();
    }

    public function getRequirementString()
    {
        return ($this->isFlag() ? '' : ($this->requiresValue ? ':' : '::'));
    }

    public function getOptionStrings()
    {
        $shortopts = '';
        $longopts = [];
        $aliases = $this->aliases;
        array_unshift($aliases, $this->name);

        foreach ($aliases as $name) {
            if (strlen($name) === 1) {
                $shortopts .= $this->getOptionString($name);
            } else {
                $longopts[] = $this->getOptionString($name);
            }
        }

        return [$shortopts, $longopts];
    }

    public function __toString()
    {
        return $this->value;
    }
}
