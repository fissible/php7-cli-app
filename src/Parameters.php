<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Events\AddParameterEvent;

class Parameters
{
    protected array $arguments;

    protected Arguments $Arguments;

    protected array $options;

    protected Options $Options;

    public function __construct(Collection $Options = null, Collection $Arguments = null)
    {
        $this->setOptions($Options);
        $this->setArguments($Arguments);
    }

    /**
     * Get the CLI arguments
     * @return array
     */
    public static function argv()
    {
        global $argv;
        $args = [];
        if (isset($argv) && is_array($argv)) {
            $args = $argv;
            if (is_file($args[0])) {
                // remove script name
                $args = array_slice($args, 1);
            }
        }

        return $args;
    }

    public function drop($name)
    {
        $pulled = $this->Arguments->pull(function (Argument $A) use ($name) {
            return $A->name() === $name;
        });
        if ($pulled->empty()) {
            $pulled = $this->Options->pull(function (Option $O) use ($name) {
                return $O->name() === $name;
            });
        }

        return !$pulled->empty();
    }

    public function getArgument($name): ?Argument
    {
        return $this->Arguments->first(function ($Argument) use ($name) {
            return $Argument->name() === $name;
        });
    }

    public function hasArgument($name)
    {
        $Argument = $this->getArgument($name);

        return !is_null($Argument);
    }

    public function hasRequiredArgument($name)
    {
        $Argument = $this->requiredArguments()->first(function ($Argument) use ($name) {
            return $Argument->name() === $name && !is_null($Argument->value());
        });
        return !is_null($Argument);
    }

    public function hasOption($name): bool
    {
        $name = trim($name, '-:');
        foreach ($this->Options as $Option) {
            if ($Option->is($name)) return true;
        }
        return false;
    }

    public function getArguments($skipCheckForMissing = true): Collection
    {
        if ($skipCheckForMissing !== true) {
            $this->validateHasRequiredArguments();
        }
        return $this->Arguments;
    }

    public function getOption($name): ?Option
    {
        foreach ($this->Options as $Option) {
            if ($Option->is($name)) return $Option;
        }
        return null;
    }

    public function getOptionValue($name, $default = null)
    {
        if ($Option = $this->getOption($name)) {
            return $Option->value() ?? $default;
        }
        return null;
    }

    public function getOptions()
    {
        return $this->Options;
    }

    public function isMissingRequiredArgument($name = null)
    {
        foreach ($this->Arguments as $Argument) {
            if (is_null($name) || $Argument->is($name)) {
                if ($Argument->isRequired() && $Argument->empty()) {
                    return $Argument->name();
                }
            }
        }
        return false;
    }

    public function optionalArguments(): Collection
    {
        return $this->Arguments->filter(function ($Argument) {
            return !$Argument->isRequired();
        });
    }

    public function requiredArguments(): Collection
    {
        return $this->Arguments->filter(function ($Argument) {
            return $Argument->isRequired();
        });
    }

    public function parseArguments()
    {
        $this->arguments = static::argv();

        // parse out options
        $nextValueMatched = false;
        foreach ($this->arguments as $key => $value) {
            if ($nextValueMatched) {
                $nextValueMatched = false;
                unset($this->arguments[$key]);
                continue;
            }

            $nextKey = $key + 1;
            $nextKey = isset($this->arguments[$nextKey]) ? $nextKey : null;
            $nextVal = !is_null($nextKey) ? $this->arguments[$nextKey] : null;

            if ($Option = $this->getOption($value)) {
                if (!$Option->isFlag() && $Option->equals($nextVal)) {
                    $nextValueMatched = true;
                }
                unset($this->arguments[$key]);
                continue;
            }

            // Arguments are filled in order (no way to indicate null|void entries on CLI)
            foreach ($this->Arguments as $Argument) {
                if ($Argument->empty()) {
                    $Argument->setValue($value);
                    continue 2;
                }
            }
        }

        return $this;
    }

    public function parseOptions()
    {
        $this->options = $this->parse();

        foreach ($this->Options as $Option) {
            foreach ($this->options as $name => $value) {
                if ($Option->is($name)) {
                    $Option->setValue($value);
                }
            }
        }

        return $this;
    }

    public function setArguments(Collection $Arguments = null)
    {
        $this->Arguments = new Arguments($this, $Arguments ? $Arguments->toArray() : []);
        $this->parseArguments();

        return $this;
    }

    public function setOptions(Collection $Options = null)
    {
        $this->Options = new Options($this, $Options ? $Options->toArray() : []);
        $this->parseOptions();
        
        return $this;
    }

    public function parse(): array
    {
        $shortopts = '';
        $longopts = [];
        foreach ($this->Options as $Option) {
            [$shorts, $longs] = $Option->getOptionStrings();
            if (!empty($shorts)) {
                $shortopts .= $shorts;
            }
            if (!empty($longs)) {
                $longopts = array_merge($longopts, $longs);
            }
        }

        return getopt($shortopts, $longopts);
    }

    public function validateHasRequiredArguments()
    {
        if ($argName = $this->isMissingRequiredArgument()) {
            throw new Exceptions\MissingArgumentException($argName);
        }
        return $this;
    }
    
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        foreach ($this->Arguments as $Argument) {
            if ($Argument->is($name) && !$Argument->empty()) {
                return $Argument->value();
            }
        }

        foreach ($this->Options as $Option) {
            if ($Option->is($name) && !$Option->empty()) {
                return $Option->value();
            }
        }

        return null;
    }
}