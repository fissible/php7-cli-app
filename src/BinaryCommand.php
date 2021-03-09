<?php declare(strict_types=1);

namespace PhpCli;

class BinaryCommand extends Command
{
    protected Application $Application;

    protected string $binary;

    public function __construct(Application $Application, ?string $binary = null)
    {
        $this->Application = $Application;
        $this->setBinary($binary);
    }

    public function run()
    {
        $options  = $this->Application->Parameters->options;
        $arguments = $this->Application->Parameters->arguments;

        return $this->pipe($options, $arguments);
    }

    public function setBinary(string $binary)
    {
        if (is_null($binary)) {
            $binary = $this->Application->getScript();
        }
        if (!file_exists($binary) || !is_executable($binary)) {
            throw new \PhpCli\Exceptions\BinaryNotFoundException($binary);
        }

        $this->binary = $binary;
    }

    /**
     * @param array $options
     * @param array $arguments
     * @return array
     */
    public function exec(array $options = [], ...$arguments)
    {
        $output = [];
        $exe = $this->compile($options, ...$arguments);

        if (!empty($exe)) {
            exec($exe, $output);
        }

        return $output;
    }

    /**
     * @param array $options
     * @param array $arguments
     * @return null|string
     */
    public function pipe(array $options = [], ...$arguments)
    {
        $output = null;
        $exe = $this->compile($options, ...$arguments);

        if (!empty($exe)) {
            passthru($exe, $output);
        }

        return $output;
    }

    public function compile(array $options = [], $arguments = [])
    {
        $exe = $this->binary;

        if (!empty($options)) {
            foreach ($options as $name => $value) {
                $name = (strlen($name) > 1 ? '--' : '-') . $name;
                if (is_bool($value)) {
                    if ($value === true) {
                        $exe .= ' ' . $name;
                    }
                } elseif (is_scalar($value)) {
                    $exe .= ' ' . $name . ' ' . (string) $value;
                }
            }
        }

        if (!empty($arguments)) {
            foreach ($arguments as $arg) {
                $exe .= ' ' . $arg;
            }
        }

        return trim($exe);
    }
}