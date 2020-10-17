<?php

declare(strict_types=1);

namespace PhpCli;

class Hatchery extends Application
{
    public function __construct(string $script, ...$options)
    {
        parent::__construct($script, ...$options);

        $this->defineMenu(Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);
    }

    /**
     * Display the main menu with a prompt for selection.
     * $selection = $Hatchery->do('PromptMainMenu');
     * 
     * @return mixed
     */
    protected function doPromptMainMenu()
    {
        $prompt = 'Choose: ';
        $returnOptionKey = true;

        return $this->menu(Application::MAIN_MENU, $prompt, $returnOptionKey);
    }

    /**
     * Start interactive binary script writing session.
     */
    public function doHatch()
    {
        $fileContent = $this->getBinaryContent();
        $saveTo = $this->prompt('Write binary to (directory): ');

        if (empty($saveTo)) {
            $saveTo = getcwd() . DIRECTORY_SEPARATOR;
        }

        if (substr($saveTo, -1, 1) === DIRECTORY_SEPARATOR) {
            $binary = $this->prompt('Name of binary: ');
            $saveTo .= $binary;
        }

        if (is_dir($saveTo)) {
            $binary = $this->prompt('Name of binary: ');
            $saveTo .= DIRECTORY_SEPARATOR . $binary;
        }

        if (0 !== strpos($saveTo, DIRECTORY_SEPARATOR)) {
            $saveTo = getcwd() . DIRECTORY_SEPARATOR . $saveTo;
        }

        if (file_put_contents($saveTo, $fileContent)) {
            $this->linef('New binary written to %s', $saveTo);
            chmod($saveTo, 0755);
        }
    }

    public function doHelp()
    {
        $this->line($this->getHelp());

        $arguments = $this->args->parse();
        if (count($arguments)) {
            // @todo - print options nicer (in Application)
            print_r($arguments);
        }
    }

    private function getBinaryContent()
    {
        /*
            What version of php is required?
            Where am I? (pwd)
            Where should I write this file
            What is the name of your CLI app?
        */

        $name = $this->prompt('New CLI app name [eg. Hatchery]: ');
        $className = ucfirst($name);
        $phpVersionMinimum = $this->prompt(sprintf('Minimum PHP version required [eg. %s]: ', PHP_VERSION));
        $phpVersionMaximum = $this->prompt('Maximum PHP version allowed [eg. 7.9.9]: ');
        $path = dirname(dirname(__FILE__));

        if ($phpVersionMinimum) {
            $phpVersionMinimum = <<<EOT
if (version_compare(phpversion(), '$phpVersionMinimum', '<')) {
    print "ERROR: PHP version must be greater than or equal to $phpVersionMinimum";
    exit(1);
}

EOT;
        }

        if ($phpVersionMaximum) {
            $phpVersionMaximum = <<<EOT
if (version_compare(phpversion(), '$phpVersionMaximum', '>')) {
    print "ERROR: PHP version must be less than or equal to $phpVersionMaximum";
    exit(1);
}

EOT;
        }


        $template = <<<EOT
#!/usr/bin/env php
<?php
$phpVersionMinimum
$phpVersionMaximum
require_once('$path/vendor/autoload.php');

class $className extends PhpCli\Application {

    public function __construct(string \$script, ...\$options)
    {
        parent::__construct(\$script, ...\$options);

        \$this->defineMenu(PhpCli\Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);
    }

    /**
     * Display the main menu with a prompt for selection.
     * \$selection = \$app->do('PromptMainMenu');
     * 
     * @return mixed
     */
    protected function doPromptMainMenu()
    {
        \$prompt = 'Choose: ';
        \$returnOptionKey = true;

        return \$this->menu(PhpCli\Application::MAIN_MENU, \$prompt, \$returnOptionKey);
    }

}

\$app = new $name(
    basename(__FILE__),
    ['h', 'help'], // flags
    [], // params that require values
    []  // params with optional values
);


\$app->line('Press Ctrl/Cmd+D to quit');

\$selection = \$app->do('PromptMainMenu');

if (\$selection) {
    \$app->linef('You selected "%s"', \$selection);
}

\$app->exit();

EOT;
        return $template;
    }
}