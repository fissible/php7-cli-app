<?php declare(strict_types=1);

namespace PhpCli;

class Hatchery extends Application
{
    public function __construct()
    {
        parent::__construct();

        $this->defineMenu(Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);
    }

    /**
     * Start interactive binary script writing session.
     */
    public function doHatch()
    {
        $name = $this->prompt('New CLI app name (eg. Hatchery): ', null, ['required']);

        // @todo Install autocompletion script? /etc/bash_completion.d/
        $saveTo = $this->prompt('Write binary to (directory): ');

        if (empty($saveTo)) {
            $saveTo = getcwd() . DIRECTORY_SEPARATOR;
        }

        if (substr($saveTo, -1, 1) === DIRECTORY_SEPARATOR) {
            $binary = $this->prompt('Name of binary: ' . Output::color($saveTo, 'light_purple'));
            $saveTo .= $binary;
        }

        if (is_dir($saveTo)) {
            $saveTo = rtrim($saveTo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $binary = $this->prompt('Name of binary: ' . Output::color($saveTo, 'light_purple'));
            $saveTo .= $binary;
        }

        if (0 !== strpos($saveTo, DIRECTORY_SEPARATOR)) {
            $saveTo = getcwd() . DIRECTORY_SEPARATOR . $saveTo;
        }

        $File = new Filesystem\File($saveTo);

        if ($File->exists() && !$this->promptYesNo('File already exists at the indicated path, overwrite?')) {
            $this->line('Aborting.');
            $this->exit();
        }

        $fileContent = $this->getBinaryContent($name, $binary);

        if ($File->write($fileContent)) {
            $this->linef('New binary written to %s', $saveTo);
            $File->chmod(0755);
        }
    }

    private function getBinaryContent(string $name, string $binary)
    {
        
        $className = ucfirst($name);
        $commandClassName = 'Run' . $className . '';
        $phpVersionMinimum = $this->prompt('Minimum PHP version required: ', PHP_VERSION);
        $phpVersionMaximum = $this->prompt('Maximum PHP version allowed [none]: ');
        $path = dirname(dirname(__FILE__));
        $options = [];
        $optionsString = '$options';
        $arguments = [];
        $argumentsString = '$arguments';

        $this->linef('Now you can define options/flags and arguments, eg. %s [options...] <argument>', $binary);

        // Define Options
        if ($this->promptYesNo('Do you want to define options/flags interactively? ', false)) {
            $adding = true;
            while ($adding) {
                $options[] = [
                    'name' => $this->prompt('What is the option name? (eg. "file", "f", or "f|file"): ', null, ['required']),
                    'requiresValue' => $this->prompt('Does this option require a value or is it a flag? [yes/no/Flag]: '),
                    'description' => $this->prompt('Please provide a description for the help function: '),
                    'defaultValue' => $this->prompt('Please provide a default value [N/A]: ')
                ];

                $adding = $this->promptYesNo('Do you want to define another option or flag? ', true);
            }
        }

        // Define Arguments
        if ($this->promptYesNo('Do you want to define arguments interactively? ', false)) {
            $adding = true;
            while ($adding) {
                $arguments[] = [
                    'name' => $this->prompt('What is the argument name? (eg. "path"): ', null, ['required']),
                    'requiresValue' => $this->promptYesNo('Is this argument required? ', false),
                    'defaultValue' => $this->prompt('Please provide a default value [N/A]: ')
                ];

                $adding = $this->promptYesNo('Do you want to define another argument? ', true);
            }
        }

        if ($count = count($options)) {
            $optionsString = 'array_merge($options, [';
            foreach ($options as $key => $option) {
                $optionsString .= '
            new PhpCli\Option(
                $flagName = \'' . $option['name'] . '\',
                $requiresValue = ' . ($option['requiresValue'] === '' ? 'null' : strtolower(substr($option['requiresValue'], 0, 1)) === 'y') . ',
                $description = \'' . $option['description'] . '\',
                $defaultValue = ' . ($option['defaultValue'] === '' ? 'null' : var_export($option['defaultValue'], true)) . '
            )';
                if ($key < ($count - 1)) {
                    $optionsString .= ',';
                } else {
                    $optionsString .= "\n\t";
                }
            }
            $optionsString .= "\n\t\t".'])';
        }

        if ($count = count($arguments)) {
            $argumentsString = 'array_merge($arguments, [';
            foreach ($arguments as $key => $argument) {
                $argumentsString .= '
            new PhpCli\Argument(
                $argumentName = \'' . $argument['name'] . '\',
                $requiresValue = ' . var_export($argument['requiresValue'], true) . ',
                $defaultValue = ' . ($argument['defaultValue'] === '' ? 'null' : var_export($argument['defaultValue'], true)) . '
            )';
                if ($key < ($count - 1)) {
                    $argumentsString .= ',';
                } else {
                    $argumentsString .= "\n\t";
                }
            }
            $argumentsString .= "\n\t\t".'])';
        }

        if ($phpVersionMinimum) {
            $phpVersionMinimum = <<<EOT
if (version_compare(phpversion(), '$phpVersionMinimum', '<')) {
    print "ERROR: PHP version must cannot be less than $phpVersionMinimum";
    exit(1);
}
EOT;
        }

        if ($phpVersionMaximum) {
            $phpVersionMaximum = <<<EOT
if (version_compare(phpversion(), '$phpVersionMaximum', '>')) {
    print "ERROR: PHP version cannot be greater than $phpVersionMaximum";
    exit(1);
}
EOT;
        }


        $template = <<<EOT
#!/usr/bin/env php
<?php
if (php_sapi_name() !== 'cli') {
    exit;
}

$phpVersionMinimum
$phpVersionMaximum

define('SCRIPT', basename(__FILE__));

require_once('$path/vendor/autoload.php');

use PhpCli\Command;
use PhpCli\Cursor as Cursor;

\$error = null;

pcntl_async_signals(true);
pcntl_signal(SIGINT, 'quit');
pcntl_signal(SIGTERM, 'quit');
register_shutdown_function('cleanup');
set_error_handler(function (\$errno, \$errstr, \$errfile, \$errline) {
    if (0 === error_reporting()) {
        return false;
    }
    throw new \Exception(\$errstr.' in '.\$errfile.':'.\$errline."\\n\\tException thrown");
});
function cleanup()
{
    global \$app, \$error;
    
    Cursor::show();

    if (isset(\$app->screen) && \$app->screen !== false) {
        system('tput rmcup');
    }

    PhpCli\stty::reset();

    if (!is_null(\$error)) {
        print \$error."\\n";
    }
}
function quit(int \$code = 0)
{
    exit(\$code);
}

/**
 * APPLICATION
 */
final class $className extends PhpCli\Application {
    
    protected string \$script = SCRIPT;

    protected Command \$defaultCommand;

    /**
     * Relative to executable; `pwd`/\$configFile
     * eg. 'config.json'
     */
    public static string \$configFile;

    /**
     * Define the binary flags, eg.
     *  binry --flag -v
     * 
     * The -h (or --help) is added in Application::defineOptions().
     */
    protected function defineOptions(array \$options = []): array
    {
        return parent::defineOptions($optionsString);
    }

    /**
     * Define the arguments the binary accepts (they will be filled in order they are defined), eg.
     *   binry [options...] source destination
     */
    protected function defineArguments(array \$arguments = []): array
    {
        return parent::defineArguments($argumentsString);
    }

    protected function init(): void
    {
        parent::init(); // invokes createTables()

        \$this->defineMenu(PhpCli\Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);

        \$this->defaultCommand = new $commandClassName(\$this);

        // Startup actions
        // ...
    }

    /**
     * Execute the SQL statements to create new tables. Merge the argument
     * "\$statements" to preserve any parent SQL statements.
     * 
     * @param array
     */
    protected function createTables(\$statements = [])
    {      
        parent::createTables(array_merge(\$statements, [
            /*
            '<CUSTOM SQL STATEMEMT>',
            '<CUSTOM SQL STATEMEMT>'
            */
        ]));
    }
}

/**
 * COMMAND
 */
class $commandClassName extends PhpCli\Command
{
    public function run()
    {
        \$this->line('Press ^C to quit');

        do {
            if (\$selection = \$this->app()->menu(PhpCli\Application::MAIN_MENU)->prompt()) {
                \$this->linef('You selected "%s"', \$selection);
            }
        } while (\$selection);
    }
}

\$app = new $name();
\$app->run();
EOT;
        return $template;
    }
}