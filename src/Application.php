<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Database\Driver as DatabaseDriver;
use PhpCli\Database\Query;
use PhpCli\Events\Abort;
use PhpCli\Events\Event;
use PhpCli\Exceptions\ConfigNotFoundException;
use PhpCli\Exceptions\ValidationException;
use PhpCli\Filesystem\File;
use PhpCli\Reporting\Logger;
use PhpCli\Traits\RequiresBinary;
use PhpCli\Validation\Validator;

/**
 * Application class
 * 
 * Instantiate with the command (or binary) name for use with the "--help" flag, eg.
 * $ cmnd --help
 * Usage: cmnd [options] [-f] <file> [args...]
 * 
 * Terminology:
 *  parameter - any string supplied to the command
 *  option - flag or switch that modifies the operation of a command, may accept or require a value
 *  argument - an item of information provided to the program, may require a value
 */
class Application
{
    use RequiresBinary;

    /**
     * Relative to executable; `pwd`/$configFile.
     * eg. 'config.json'
     */
    public static string $configFile;

    public Parameters $Parameters;

    public Output $output;

    public bool $screen = false;

    public const MAIN_MENU = '__main_menu';

    protected static \PDO $db;

    protected Command $defaultCommand;

    protected Config $Config;

    protected string $defaultPrompt = ' > ';

    protected array $instances;

    protected array $providers;

    protected string $script;

    private Router $Router;

    private $input;

    private array $menus;

    private $return;

    private int $returnCode = 0;

    const MAIN_MENU_NAME = 'commands';
 

    public function __construct(array $options = [], array ...$arguments)
    {
        global $argv;
        $this->script = $argv[0];

        $this->Parameters = $this->defineParameters(
            $this->defineOptions($options),
            $this->defineArguments($arguments)
        );
        $this->Router = new Router($this);
        $this->output = new Output();
        $this->menus = [];

        // readline_completion_function([__CLASS__, 'completionCallback']);

        $Help = $this->Parameters->getOption('help');
        if ($Help && $Help->value()) {
            $this->doHelp();
            $this->exit();
        }

        $this->init();
    }

    public static function hash(...$args)
    {
        return sha1(var_export($args, true));
    }

    public static function cacheResult(callable $callback, ...$args)
    {
        static $cache = [];
        $callbackHash = static::closureId($callback);
        $hash = static::hash(...$args);

        if (!isset($cache[$callbackHash]) || !isset($cache[$callbackHash][$hash])) {
            $cache[$callbackHash] = [
                $hash => $callback(...$args)
            ];
        }

        return $cache[$callbackHash][$hash];
    }

    private static function closureId(callable $callback)
    {
        $rf = new \ReflectionFunction($callback);
        return sha1($rf->getFileName().$rf->getEndLine());
    }

    public function checkMissingParameters()
    {
        $this->Parameters->validateHasRequiredArguments();
    }

    public function clear()
    {
        if ($this->screen) {
            system('tput clear');
        } else {
            system('clear');
        }
    }

    public function pause(string $continuePrompt = '[ENTER to continue]')
    {
        exec("read -r -p '$continuePrompt'");
    }

    public function screen()
    {
        $this->screen = true;
        system('tput smcup');
    }

    /**
     * Used for readline_completion_function()
     * @todo deprecate
     */
    protected function completionCallback($input, $index): array
    {
        return $this->Router->getRoutes()->map(function (Route $Route) {
            return $Route->getName();
        })->toArray();
    }

    /**
     * Execute the sql statements to create new tables. Overwrite in extending classes
     * and merge the argument "$statements" to preserve parent SQL statements, eg.
     * 
     *     parent::createTables(array_merge($statements, ['SQL string']));
     * 
     * @param array
     */
    protected function createTables($statements = [])
    {
        if (isset(static::$db)) {
            foreach ($statements as $statement) {
                static::$db->exec($statement);
            }
        }
    }

    /**
     * Set up database connection.
     * 
     * @return bool
     */
    protected function databaseInit(): bool
    {
        $created = false;
        if (!isset(static::$db)) {
            static::$db = DatabaseDriver::create($this->config()->get('database'));
            $created = true;
        }

        $this->bindInstance(DatabaseDriver::class, static::$db);
        Query::app($this);

        return $created;
    }

    /**
     * Check if there is a config file and it contains database config fields.
     * 
     * @return bool
     */
    protected function hasDatabaseConfig(): bool
    {
        if (!$this->getConfigFilepath()) {
            return false;
        }
        $config = $this->config()->get('database');

        return !empty($config);
    }

    /**
     * Check if there is a config file and it contains database config fields.
     * 
     * @return bool
     */
    protected function hasLoggerConfig(): bool
    {
        if (!$this->getConfigFilepath()) {
            return false;
        }
        $config = $this->config()->get('logger');

        return !empty($config);
    }

    /**
     * Do initial application-specific setup.
     */
    protected function init(): void
    {
        // Set up services
        if ($this->hasDatabaseConfig()) {
            $created = $this->databaseInit();
            
            if ($created) {
                $this->createTables();
            }
        }
        if ($this->hasLoggerConfig()) {
            $config = $this->config()->get('logger');
            $this->defineProvider(Logger::class, function ($app) {
                return Logger::create($config);
            });

            Log::app($this);
        }
    }

    /**
     * Bind a route name with a callable or Command instance.
     * 
     * @param string $name
     * @param callable|Command $action
     * @return self
     */
    public function bind(string $name, $action): self
    {
        $this->Router->bind(new Route($name, $action));

        return $this;
    }

    /**
     * @param string $class
     * @param mixed $instance
     * @return void
     */
    public function bindInstance(string $class, $instance): void
    {
        if (!isset($this->instances)) {
            $this->instances = [];
        }

        $this->instances[$class] = $instance;
    }

    public function config(): Config
    {
        $path = $this->getConfigFilepath();
        if (!isset($this->Config) && $path) {
            $this->Config = new Config($path);
        }

        if (isset($this->Config)) {
            return $this->Config;
        }

        throw new ConfigNotFoundException($path ?? '<no path supplied>');
    }

    /**
     * @param string $class
     * @param mixed $provider
     * @return void
     */
    public function defineProvider(string $class, $provider): void
    {
        if (!is_callable($provider)/* && !($provider instanceof Prrovider)*/) {
            throw new InvalidArgumentException();
        }

        if (!isset($this->providers)) {
            $this->providers = [];
        }

        $this->providers[$class] = $provider;
    }

    public function instance(string $class)
    {
        if (isset($this->instances) && isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        return null;
    }

    /**
     * Resolve an instance of class.
     */
    public function make(string $class)
    {
        if (isset($this->providers) && isset($this->providers[$class])) {
            // create new instance
            if (is_callable($this->providers[$class])) {
                return $this->providers[$class]($this);
            }
            // return $this->providers[$class]->make($this);
        }

        return null;
    }

    public function route($input, array $params = [])
    {
        $returnCode = 0;
        $return = $this->Router->route($input, $params);

        if (is_int($return)) {
            $returnCode = $return;
        }

        if ($returnCode !== 0) {
            throw new Abort($returnCode);
        }

        return $return;
    }

    /**
     * Provide a FQN of a Command to instantiate and run. eg.
     *   $this->run(\My\App\ListCommand::class);
     * 
     * @param Command $command
     * @return mixed
     */
    public function run($command = null)
    {
        $this->checkMissingParameters();

        $defaultCommand = $command;

        try {
            if (is_null($command) && isset($this->defaultCommand)) {
                $this->return = $this->defaultCommand->run();

                if (is_int($this->return)) {
                    $this->returnCode = $this->return;
                }
            } elseif (!is_null($command) && $command instanceof Command) {
                return $command->run();
            } else {
                $this->mainLoop($defaultCommand);
            }
        } catch (Event $e) {
            $this->handle($e);
        }

        return $this->return ?? $this->returnCode;
    }

    public function mainMenu()
    {
        if ($this->hasMenu(self::MAIN_MENU_NAME)) {
            if ($this->screen) {
                $this->clear();
            }
            $this->menu(self::MAIN_MENU_NAME, 'Main Menu:');
        }
    }

    /**
     * The main application loop; prompts for and executes application commands
     * 
     * @param string|null $defaultCommand
     */
    public function mainLoop(?string $defaultCommand = null): void
    {
        if ($defaultCommand) {
            $command = $defaultCommand;
        } else {
            $command = $this->promptCommand($defaultCommand);
        }
        
        while ($command) {
            $this->return = $this->route($command);
            $command = $this->promptCommand($defaultCommand);
        }
    }

    /**
     * Enter a discrete command loop for the provided menu
     * 
     * @param string $name
     * @param callable $handler
     * @param string $exitCommand
     */
    public function menuLoop(string $menu, callable $handler, $prompt = null, $title = null, string $exitCommand = '')
    {
        $return = null;
        $command = true;
        while ($return !== false) {
            $command = $this->menuPrompt($menu, $prompt, $title);
            if ($command === $exitCommand || $command === '') break;
            
            $return = $handler($this, $command);
        }
        
        return $return;
    }

    protected function handle(Event $Event)
    {
        switch (get_class($Event)) {
            case Abort::class:
                $this->returnCode = $Event->code;
                $this->exit();
            break;
        }
    }

    /**
     * Prompt the user to enter a command (valid Route name).
     * 
     * @param string|null $defaultCommand
     * @return mixed
     */
    protected function promptCommand(?string $defaultCommand = null): ?string
    {
        if (extension_loaded('readline')) {
            readline_completion_function(function ($input, $index) {
                return $this->completionCallback($input, $index);
            });
        }

        $this->mainMenu();

        $command = $this->prompt();

        if (is_null($command)) {
            $command = $defaultCommand;
        }

        if (strtolower($command) === 'exit') {
            throw new Abort();
        }

        if (!$this->Router->has($command)) {
            $this->error('Invalid command.');
            $command = $defaultCommand;
        }

        return $command;
    }

    /**
     * @param string $name
     * @param array $items
     * @param string $prompt
     * @param string|null $label
     * @return $this
     */
    public function defineMenu(string $name, array $items, string $prompt = 'Choose: ', ?string $label = null): self
    {
        $this->menus[$name] = new Menu($this, $items, $prompt, $label);

        return $this;
    }

    /**
     * @param array $items
     * @param string $prompt
     * @param string|null $label
     * @return $this
     */
    public function defineMainMenu(array $items, string $prompt = 'Choose: ', ?string $label = null): self
    {
        return $this->defineMenu(self::MAIN_MENU_NAME, $items, $prompt, $label);
    }

    /**
     * @param string $name
     * @param $arguments
     */
    public function do(string $name, ...$arguments)
    {
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

    /**
     * @param string $line
     * @param int $indent
     */
    public function error(string $line = ''): void
    {
        $this->output->error($line);
    }

    /**
     * Invoke a binary and pass through flags and arguments
     * 
     * @param string $binary
     */
    public function exec(string $binary)
    {
        $Command = new BinaryCommand($this, $binary);
        $options  = $this->Parameters->options;
        $arguments = $this->Parameters->arguments;

        $this->linef('Running %s', $Command->compile($options, $arguments));

        return $Command->exec($options, $arguments);
    }

    /**
     * Invoke a binary and pass through flags and arguments
     * 
     * @param string $binary
     */
    public function pipe(string $binary)
    {
        $Command = new BinaryCommand($this, $binary);
        $options  = $this->Parameters->options;
        $arguments = $this->Parameters->arguments;

        $this->linef('Running %s', $Command->compile($options, $arguments));

        return $Command->pipe($options, $arguments);
    }

    public function exit(int $code = null)
    {
        $exitCode = $code ?? $this->returnCode ?? 0;

        if (isset($this->return) && is_scalar($this->return)) {
            if ($exitCode === 0) {
                $this->line($this->return);
            } else {
                $this->error($this->return);
            }
        }

        exit($exitCode);
    }

    public function getArgument($name): Argument
    {
        return $this->getArguments()->first(function ($Argument) use ($name) {
            return $Argument->name() === $name;
        });
    }

    public function getArguments($skipCheckForMissing = true): Collection
    {
        return $this->Parameters->getArguments($skipCheckForMissing);
    }

    /**
     * Print the help text
     */
    public function getHelp()
    {
        $Options = $this->Parameters->Options;
        $requiredArguments = $this->Parameters->requiredArguments();
        $optionalArguments = $this->Parameters->optionalArguments();
        $line = 'Usage: ' . $this->script;

        if (count($Options)) {
            $line .= ' [options...]';
        }

        foreach ($requiredArguments as $requiredArgument) {
            $line .= ' <' . $requiredArgument->name() . '>';
        }
        foreach ($optionalArguments as $optionalArgument) {
            $line .= ' ' . $optionalArgument->name();
        }

        // eg. "Usage command [options...] <required>"
        $this->output->buffer()->printl($line);

        $widestOption = 0;
        foreach ($Options as $Option) {
            if (($len = strlen($Option->getCliFlagsString())) > $widestOption) {
                $widestOption = $len;
            }
        }
        foreach ($Options as $Option) {
            $this->output->buffer()->printl(
                ' ' .
                str_pad($Option->getCliFlagsString(), $widestOption + 2) .
                $Option->description()
            );
        }

        $bufferArray = $this->output->flush();

        return implode($bufferArray);
    }

    /**
     * @param string $name
     * @return Menu
     */
    public function getMenu(string $name)
    {
        if (!$this->hasMenu($name)) {
            throw new \InvalidArgumentException(sprintf('Menu "%s" does not exist', $name));
        }

        return $this->menus[$name];
    }

    public function getOption($name): ?Option
    {
        return $this->Parameters->getOption($name);
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
        return $this->Arguments->Options;
    }

    public function getScript()
    {
        return $this->script;
    }

    public function hasMenu(string $name)
    {
        return array_key_exists($name, $this->menus);
    }

    /**
     * Generate a Table to be used as an index.
     * 
     * @param Collection $Items
     * @param array $columns
     * @param bool $numbers
     * @return Table
     */
    public function index(Collection $Items, $columns, $numbers = false): Table
    {
        $headers = array_values($columns);
        if ($numbers) array_unshift($headers, '');
        $count = 0;
        $rows = $Items->map(function ($Item) use ($columns, $numbers, &$count) {
            $count++;
            $row = [];
            if ($numbers) $row[] = $count;
            foreach ($columns as $column => $header) {
                if (is_scalar($Item)) {
                    $row[] = $Item;
                } elseif (is_object($Item)) {
                    $row[] = $Item->$column;
                } elseif (is_array($Item)) {
                    $row[] = $Item[$column];
                } else {
                    throw new \InvalidArgumentException();
                }
            }
            return $row;
        })->toArray();

        $Table = $this->table($headers, $rows);

        return $Table;
    }

    /**
     * Get the last input received from the CLI
     * 
     * @return string|null
     */
    public function last(): ?string
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
     * Output the menu item list.
     * 
     * @param string|array $nameOrOptions
     * @param string|null $title
     * @return Menu
     */
    public function menu($nameOrOptions, string $title = null, string $prompt = null, string $label = null): Menu
    {
        if ($nameOrOptions instanceof Collection) {
            $nameOrOptions = $nameOrOptions->toArray();
        }
        if (is_array($nameOrOptions)) {
            $Menu = new Menu($this, $nameOrOptions, $prompt, $label);
        } elseif (is_string($nameOrOptions)) {
            $Menu = $this->getMenu($nameOrOptions);
        } else {
            throw new \InvalidArgumentException();
        }
        

        if ($title) {
            $this->line($title);
        }
        
        $Menu->list();

        return $Menu;
    }

    /**
     * Output the menu item list and prompt/return selection.
     * 
     * @param string|array $nameOrOptions
     * @param string|null $prompt
     * @param string|null $title
     * @param bool $returnValue
     * @return string|int|null
     */
    public function menuPrompt($nameOrOptions, string $prompt = null, string $title = null, bool $returnValue = false)
    {
        $Menu = $this->menu($nameOrOptions, $title);
        $Menu->setReturnValue($returnValue);
        $selection = $Menu->prompt($prompt);

        if (!is_null($selection) && (is_string($selection) && strlen($selection) || is_int($selection))) {
            return $selection;
        }
        return null;
    }

    /**
     * Get an option by name and optionally set the value
     * 
     * @param  string $name
     * @return mixed
     */
    public function Option($name, $value = null)
    {
        if ($Option = $this->Parameters->getOption($name)) {
            if (!is_null($value)) {
                $Option->setValue($value);
            }
        }
        return $Option;
    }

    /**
     * Input/Output
     */

    /**
     * @param string|null $prompt
     * @param mixed $default
     * @param array $rules
     * @param array $messages
     * @return mixed
     */
    public function prompt(?string $prompt = null, $default = null, array $rules = [], array $messages = [])
    {
        if (is_null($prompt)) {
            $prompt = $this->defaultPrompt;
        }

        $prompt = Input::prepare_prompt($prompt, $default);
        $validator = empty($rules) ? null : Input::validator($rules, $messages);

        return $this->input = Input::prompt($prompt, $default, $validator);
    }

    public function promptDate(?string $prompt = null, string $format = 'mm/dd/yyyy', ?string $default = null, array $rules = [], array $messages = []): ?\DateTime
    {
        if (is_null($prompt)) {
            $prompt = 'Enter date (#format): ';
        }

        if (false !== strpos($prompt, '#format')) {
            $prompt = str_replace('#format', $format, $prompt);
        }

        $rules[] = 'date:'.$format;

        if ($default) {
            $default = (new \DateTime($default))->format($format);
        }

        if ($input = $this->prompt($prompt, $default, $rules, $messages)) {
            return \DateTime::createFromFormat($format, $input);
        }

        return $default;
    }

    /**
     * @param string $prompt
     * @return string
     */
    public function promptSecret(string $prompt): string
    {
        return Input::promptSecret($prompt);
    }

    /**
     * Prompt for username and password on the command line.
     *  list($username, $password) = $this->promptUsernamePassword();
     * 
     * @param null|string $usernamePrompt
     * @param null|string $passwordPrompt
     * @return array
     */
    public function promptUsernamePassword(?string $usernamePrompt = null, ?string $passwordPrompt = null)
    {
        $username = $this->prompt($usernamePrompt ?? 'What is your username? ');
        $password = $this->promptSecret($passwordPrompt ?? 'What is your password? ');
        return [$username, $password];
    }

    /**
     * @param string $prompt
     * @param string|null $default
     * @return bool|null
     */
    public function promptYesNo(string $prompt, ?bool $default = false): ?bool
    {
        $response = $this->prompt($prompt, $default ? 'y' : 'n', ['required']);
        
        if (is_string($response)) {
            return strtolower(substr($response, 0, 1)) === 'y';
        }
        return null;
    }

    /**
     * @param string $prompt
     */
    public function setDefaultPrompt(string $prompt): void
    {
        $this->defaultPrompt = $prompt;
    }

    /**
     * @param array $headers
     * @param array $rows
     * @param array $options
     * @return Table
     */
    public function table(array $headers = [], array $rows = [], array $options = []): Table
    {
        return new Table($this, $headers, $rows, $options);
    }

    /**
     * @param string $content
     * @return string
     */
    public function textEditor(string $content = '', string $comment = null): string
    {
        $binary = 'vim';
        if (!self::binaryInstalled($binary)) {
            $binary = 'vi';
            self::requireBinary($binary);
        }

        if (!empty($comment)) {
            $parts = explode("\n", $comment);
            $parts = array_map(function ($line) {
                return '# '.$line;
            }, $parts);
            $comment = implode("\n", $parts);
            $content = $comment."\n".$content;
        }

        $file = tmpfile();
        fwrite($file, $content);
        $path = stream_get_meta_data($file)['uri'];
        system($binary. ' '.$path." > `tty`");
        $content = rtrim(file_get_contents($path), "\n");
        fclose($file);

        if (!empty($comment)) {
            $parts = explode("\n", $comment);
            $lines = explode("\n", $content);
            for ($i = 0; $i < count($parts); $i++) {
                if (isset($lines[$i]) && $lines[$i] === $parts[$i]) {
                    unset($lines[$i]);
                } else {
                    break;
                }
            }
            $content = implode("\n", $lines);
        }

        return $content;
    }

    public function __get($name)
    {
        return $this->Parameters->{$name};
        // if ($name === 'Options') {
        //     return $this->Parameters->Options;
        // }
        // if ($name === 'Arguments') {
        //     return $this->Parameters->Arguments;
        // }

        // throw new \InvalidArgumentException($name . ' does not exist');
    }

    protected function defineArguments(array $arguments = []): array
    {
        foreach ($arguments as $argument) {
            if (!($argument instanceof Argument)) {
                throw new \InvalidArgumentException();
            }
        }

        return $arguments;
    }

    protected function defineOptions(array $options = []): array
    {
        foreach ($options as $option) {
            if (!($option instanceof Option)) {
                throw new \InvalidArgumentException();
            }
        }

        return $options;
    }

    protected function defineParameters(array $options, array $arguments): Parameters
    {
        $Options = new Collection($options);
        $Arguments = new Collection($arguments);
        $HelpOption = new Option('h|help', null, 'This help text');
        if (!$Options->contains($HelpOption)) {
            $Options->unshift($HelpOption);
        }

        $Parameters = new Parameters($Options, $Arguments);

        return $Parameters;
    }

    private function getConfigFilepath(): ?string
    {
        if (isset(static::$configFile)) {
            $file = static::$configFile;
            if (!file_exists($file)) {
                $file = __DIR__.DIRECTORY_SEPARATOR.$file;
            }
            if (file_exists($file)) {
                return $file;
            }
            throw new ConfigNotFoundException(static::$configFile);
        }
        return null;
    }

    public function __destruct()
    {
        if ($this->screen) {
            system('tput rmcup');
        }
    }
}