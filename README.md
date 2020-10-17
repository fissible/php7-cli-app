# php7-cli-app
A PHP command line interface library and generator script

## Installation of generator script
```
$ git clone https://github.com/ajthenewguy/php7-cli-app .
$ php7-cli-app/install
Write "hatch" binary to (directory): ~/scripts
New binary written to ~/scripts/hatch
```

If the directory you install the binary to is on your path:
```
$ hatch
New CLI app name [eg. Hatchery]: Tester
Minimum PHP version required [eg. 7.4.6]:
Maximum PHP version allowed [eg. 7.9.9]:
Write binary to (directory):
Name of binary: tester
New binary written to ~/scripts/TEMP/tester
$ ./tester
Press Ctrl/Cmd+D to quit
 [1] Get started
Choose: 1
You selected "Get started"
```

## Using the generator script
```
~/scripts/TEMP > hatch
New CLI app name [eg. Hatchery]: Tester
Minimum PHP version required [eg. 7.4.6]: 7.4
Maximum PHP version allowed [eg. 7.9.9]: 7.9.9
Write binary to (directory):
Name of binary: tester
New binary written to ~/scripts/TEMP/tester
~/scripts/TEMP > ./tester
Press Ctrl/Cmd+D to quit
 [1] Get started
Choose: 1
You selected "Get started"
~/scripts/TEMP > vim ./tester
```

```
#!/usr/bin/env php
<?php
if (version_compare(phpversion(), '7.4', '<')) {
    print "ERROR: PHP version must be greater than or equal to 7.4";
    exit(1);
}

if (version_compare(phpversion(), '7.9.9', '>')) {
    print "ERROR: PHP version must be less than or equal to 7.9.9";
    exit(1);
}

require_once('~/scripts/cli/vendor/autoload.php');

class Tester extends PhpCli\Application {

    public function __construct(string $script, ...$options)
    {
        parent::__construct($script, ...$options);

        $this->defineMenu(PhpCli\Application::MAIN_MENU, [
            '1' => 'Get started'
        ]);
    }

    /**
     * Display the main menu with a prompt for selection.
     * $selection = $app->do('PromptMainMenu');
     *
     * @return mixed
     */
    protected function doPromptMainMenu()
    {
        $prompt = 'Choose: ';
        $returnOptionKey = true;

        return $this->menu(PhpCli\Application::MAIN_MENU, $prompt, $returnOptionKey);
    }

}

$app = new Tester(
    basename(__FILE__),
    ['h', 'help'], // flags
    [], // params that require values
    []  // params with optional values
);


$app->line('Press Ctrl/Cmd+D to quit');

$selection = $app->do('PromptMainMenu');

if ($selection) {
    $app->linef('You selected "%s"', $selection);
}

$app->exit();
```