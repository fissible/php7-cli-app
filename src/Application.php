<?php declare(strict_types=1);

namespace PhpCli;

class Application
{
    public Options $args;

    public Output $output;

    protected const MAIN_MENU = '__main_menu';

    private array $actions;

    private string $input;

    private string $script;

    private array $menus;

    public function __construct(string $script, ...$options)
    {
        $this->script = $script;
        $this->args = new Options(...$options);
        $this->output = new Output();
        $this->menus = [];

        $this->intercept();
    }

    public function bind(string $name, callable $callback)
    {
        $this->actions[$name] = $callback;
    }

    public function defineMenu(string $name, array $items, string $label = 'name', string $prompt = null)
    {
        $this->menus[$name] = new Menu($this, $items, $label, $prompt);
    }

    public function do(string $name, ...$arguments)
    {
        if (isset($this->actions) && array_key_exists($name, $this->actions)) {
            return $this->actions[$name](...$arguments);
        }

        $method = 'do' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], ...$arguments);
        }
        
        throw new \InvalidArgumentException(sprintf('Action "%s" does not exist', $name));
    }

    public function doHelp()
    {
        $helpBlock = $this->getHelp();

        $this->output->print($helpBlock);
    }

    public function exec(string $binary, array $parameters = [], ...$arguments)
    {
        $output = [];
        $exe = $this->compileBinaryCommand($binary, $parameters, ...$arguments);

        if (!empty($exe)) {
            print 'EXECUTING: ' . $exe . " ...\n";
            exec($exe, $output);
        }

        return $output;
    }

    public function pipe(string $binary, array $parameters = [], ...$arguments)
    {
        $exe = $this->compileBinaryCommand($binary, $parameters, ...$arguments);

        if (!empty($exe)) {
            print 'EXECUTING: ' . $exe . " ...\n";
            passthru($exe);
        }
    }

    public function exit(int $status = 0)
    {
        exit($status);
    }

    private function compileBinaryCommand(string $binary, array $parameters = [], ...$arguments)
    {
        $exe = $binary;
        if (!empty($parameters)) {
            foreach ($parameters as $param => $value) {
                if ($value === true) {
                    $exe .= ' ' . $param;
                } elseif (is_scalar($value)) {
                    $exe .= ' ' . $param . ' ' . (string) $value;
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

    public function getHelp()
    {
        
        $lineFormat = sprintf('usage: %s', $this->script).'%s';
        $arguments = $this->args->parse();
        $optionsString = count($arguments) ? ' [options]' : '';

        // return sprintf($lineFormat, $optionsString);
        $this->output->buffer()->printlf($lineFormat, $optionsString);

        $this->args->parse();
        $arguments = $this->args->getRaw();
        if (count($arguments)) {
            // @todo - print options nicer (in Application)
            foreach ($arguments as $argument) {
                /*
                [
                    'name' => $bool,
                    'flag' => true,
                    'required' => false
                ]
                */
                $this->output->buffer()->print('    ' . $argument['name']);

                if ($argument['required']) {
                    $this->output->buffer()->print(' required');
                }
                // $this->output->buffer()->print($argument['description']);
                $this->output->buffer()->printl('');
            }
        }

        $bufferArray = $this->output->flush();

        return implode($bufferArray);
    }

    public function getMenu(string $name)
    {
        if (!array_key_exists($name, $this->menus)) {
            throw new \InvalidArgumentException(sprintf('Menu "%s" does not exist', $name));
        }

        return $this->menus[$name];
    }

    /**
     * Intercept help argument
     */
    protected function intercept()
    {
        if ($this->args->help || $this->args->h) {
            $this->do('help');
            $this->exit();
        }
    }

    /**
     * Get the last input received from the CLI
     * 
     * @return string|null
     */
    public function last()
    {
        return $this->input ?? null;
    }

    /**
     * @param string $line
     * @param int $indent
     */
    public function line(string $line = '', $indent = 0): void
    {
        $this->output->line($line, $indent);
    }

    /**
     * @param string $format
     * @param ...string $vars
     */
    public function linef(string $format, ...$vars): void
    {
        $this->output->linef($format, ...$vars);
    }

    /**
     * Output the menu item list and prompt/return selection
     * 
     * @param string $name
     * @param string|null $prompt
     * @param bool $getKey
     * @return string|null
     */
    public function menu(string $name, string $prompt = null, bool $getKey = true)
    {
        $this->getMenu($name)->list();
        return $this->getMenu($name)->prompt($prompt, $getKey);
    }

    /**
     * @param string|null $prompt
     * @return string
     */
    public function prompt(string $prompt): string
    {
        $this->input = readline($prompt);
        return $this->input;
    }

    /**
     * @param array $headers
     * @param array $rows
     * @param array $options
     * @return Table
     */
    public function table(array $headers = [], array $rows = [], array $options = [])
    {
        return new Table($this, $headers, $rows, $options);
    }
}