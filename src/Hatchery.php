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
        // @todo Install autocompletion script? /etc/bash_completion.d/
        $fileContent = $this->getBinaryContent();
        $saveTo = $this->prompt('Write binary to (directory): ');

        if (empty($saveTo)) {
            $saveTo = getcwd() . DIRECTORY_SEPARATOR;
        }

        if (substr($saveTo, -1, 1) === DIRECTORY_SEPARATOR) {
            $grey = Output::color($saveTo, 'light_purple');
            $binary = $this->prompt('Name of binary: ' . $grey);
            $saveTo .= $binary;
        }

        if (is_dir($saveTo)) {
            $saveTo = rtrim($saveTo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $grey = Output::color($saveTo, 'light_purple');
            $binary = $this->prompt('Name of binary: ' . $grey);
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

        if ($File->write($fileContent)) {
            $this->linef('New binary written to %s', $saveTo);
            $File->chmod(0755);
        }
    }

    private function getBinaryContent()
    {
        $name = $this->prompt('New CLI app name (eg. Hatchery): ', null, true);
        $className = ucfirst($name);
        $commandClassName = 'Run' . $className . '';
        $phpVersionMinimum = $this->prompt('Minimum PHP version required: ', PHP_VERSION);
        $phpVersionMaximum = $this->prompt('Maximum PHP version allowed [none]: ');
        $path = dirname(dirname(__FILE__));
        $options = [];
        $optionsString = '';
        $arguments = [];
        $argumentsString = '';

        // Define Options
        if ($this->promptYesNo('Do you want to define options/flags interactively? [yes/No]', false)) {
            $adding = true;
            while ($adding) {
                $options[] = [
                    'name' => $this->prompt('What is the option name? (eg. "file", "f", or "f|file"): ', null, true),
                    'requiresValue' => $this->prompt('Does this option require a value or is it a flag? [yes/no/Flag]: ', null, false),
                    'description' => $this->prompt('Please provide a description for the help function: ', null, false),
                    'defaultValue' => $this->prompt('Please provide a default value [N/A]: ', null, false)
                ];

                $adding = $this->promptYesNo('Do you want to define another option or flag? [Yes/no]', true);
            }
        }

        // Define Arguments
        if ($this->promptYesNo('Do you want to define arguments interactively? ', false)) {
            $arguments[] = [
                'name' => $this->prompt('What is the argument name? (eg. "path"): ', null, true),
                'requiresValue' => $this->promptYesNo('Is this argument required? [yes/No]: ', false),
                'defaultValue' => $this->prompt('Please provide a default value [N/A]: ', null, false)
            ];

            $adding = $this->promptYesNo('Do you want to define another argument? [Yes/no]', true);
        }

        if ($count = count($options)) {
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
        }

        if ($count = count($arguments)) {
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
$phpVersionMinimum
$phpVersionMaximum

define('SCRIPT', basename(__FILE__));

require_once('$path/vendor/autoload.php');

/**
 * APPLICATION
 */
final class $className extends PhpCli\Application {
    
    protected string \$script = SCRIPT;

    protected string \$defaultCommand = $commandClassName::class;

    public function __construct()
    {
        parent::__construct(); // required

        \$this->defineMenu(PhpCli\Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);
    }

    /**
     * Define the binary flags, eg.
     *  binry --flag -v
     * 
     * The -h (or --help) is added in Application::defineOptions().
     */
    protected function defineOptions(): array
    {
        return parent::defineOptions([$optionsString]);
    }

    /**
     * Define the arguments the binary accepts (they will be filled in order they are defined), eg.
     *   binry [options...] source destination
     */
    protected function defineArguments(): array
    {
        return parent::defineArguments([$argumentsString]);
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
            if (\$selection = \$this->app()->menu(PhpCli\Application::MAIN_MENU)) {
                \$this->linef('You selected "%s"', \$selection);
            }
        } while (\$selection);
    }
}

(new $name())->run();
EOT;
        return $template;
    }
}