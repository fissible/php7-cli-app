<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Commands\ScreenCommand;
use PhpCli\Events\Abort;
use PhpCli\Events\Event;
use PhpCli\Events\RouteEvent;
use PhpCli\Events\ViewUpdateEvent;
use PhpCli\Exceptions\MissingArgumentException;
use PhpCli\Str;
use PhpCli\stty;
use PhpCli\Traits\RequiresBinary;
use PhpCli\UI\Component;
use PhpCli\UI\Screen;
use PhpCli\UI\View;

class ScreenApplication extends Application
{
    use RequiresBinary;

    private ?ScreenCommand $Command;

    private Screen $Screen;

    private string $viewsPath;

    protected View $View;

    protected Collection $Views;

    public function __construct(array $options = [], array ...$arguments)
    {
        self::requireBinary('tput');
        $this->Screen = new Screen($this);
        $this->Views = new Collection();

        parent::__construct($options, ...$arguments);
        // parent::screen();
    }

    /**
     * Provide a main loop body callable and run. eg.
     *   $this->run(function () {
     *       $this->screen()->draw();
     *   });
     * 
     * @param Command $command
     * @return mixed
     */
    public function run(Command $command = null)
    {
        $this->preRunValidation();

        $this->Command = $command ?? $this->defaultCommand ?? null;

        try {
            $this->mainLoop();
        } catch (Event $e) {
            $this->handle($e);
        }

        return $this->return ?? $this->returnCode;
    }

    /**
     * The main application loop; renders screen and listend for input.
     * 
     * @param Command $defaultCommand
     */
    public function mainLoop(): void
    {
        try {
            $Command = $this->Command ?? null;
            while ($Command) {
                try {
                    // if ($View = $Command->View()) {
                    //     $this->Screen->setView($View);
                    //     $this->Screen->draw();
                    // }

                    $Command = $Command->run();

                } catch (ViewUpdateEvent $Event) {
                    $this->Screen->setView($Event->View);
                } catch (RouteEvent $Event) {
                    $this->Route = $this->Router->getRoute($Event->route);
                    $Command = $this->Route->getAction();

                     // runs Command bound to route name
                    // $View = $this->route($Event->route, $Event->payload());
                    // if ($View) {
                    //     $this->Screen->setView($View);
                    // }
                }
            }
        } catch (Event $Event) {
            $this->output->linef('Unhandled Event<%s>', get_class($Event));
        }
    }

    /**
     * Output the menu item list.
     * 
     * @param string|array $nameOrOptions
     * @param string|null $title
     * @return Menu
     */
    public function menu($nameOrOptions, string $title = null, string $prompt = null, string $label = null, int $x = 0, int $y = 0): Menu
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

        return $Menu;
    }

    public function registerView(string $name, string $template, array $data = [], $config = null): self
    {
        $this->Views->set($name, $this->getScreen()->makeView($template, $data, $config));

        return $this;
    }

    public function view(string $name): ?View
    {
        return $this->Views->get($name);
    }

    /**
     * Get the specified Component.
     * 
     * @param string $name
     * @return Component|null
     */
    public function getComponent(string $name): ?Component
    {
        return $this->Screen->getComponent($name);
    }

    public function getScreen(): Screen
    {
        return $this->Screen;
    }

    public function appendComponentContent(string $componentName, $content, bool $newline = true)
    {
        if ($Component = $this->Screen->getComponent($componentName)) {
            $Component->appendContent($content, $newline);
            $this->Screen->draw();
        } else {
            throw new \Error(sprintf('Error finding the "%s" component.', $componentName));
        }
    }

    public function captureOutput(callable $callback, bool $append = true, ?string $sendTo = 'output', bool $newline = true)
    {
        $Output = new Output();
        $Output->buffer();
        
        $callback($this);

        $lines = $Output->flush();

        if ($sendTo && count($lines)) {
            $content = stripslashes(implode("\n", $lines));
            
            if ($append) {
                $this->appendComponentContent($sendTo, $content, $newline);
            } else {
                $this->setComponentContent($sendTo, $content);
            }
            
        }

        return $lines;
    }

    public function clearComponentContent(string $componentName = 'cursor'): self
    {
        $this->setComponentContent($componentName, '');
        return $this;
    }

    public function setComponentContent(string $componentName = 'cursor', $content)
    {
        // $this->clearComponentContent($componentName);

        if ($Component = $this->Screen->getComponent($componentName)) {
            $Component->setContent($content);
            $this->Screen->draw();
        } else {
            throw new \Error(sprintf('Error finding the "%s" component.', $componentName));
            // $this->error(sprintf('Error finding the "%s" component.', $componentName));
        }
    }

    public function setCursor(string $componentName = 'cursor', string $location = 'stop'): self
    {
        $this->clearComponentContent($componentName);

        if ($Component = $this->Screen->getComponent($componentName)) {
            $coords = $Component->getContentCoords($location);
            Cursor::put(...$coords);
        } else {
            throw new \Error(sprintf('Error finding the "%s" component.', $componentName));
            // $this->error(sprintf('Error finding the "%s" component.', $componentName));
        }

        return $this;
    }

    public function setData(string $key, $value)
    {
        $this->Screen->setData($key, $value);
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

        // $this->mainMenu();

        if ($Component = $this->View->getComponent('cursor')) {
            [$y, $x] = $Component->getContentCoords();
            Cursor::put($y, $x);
        }

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
     * Prompt for an additional value. Example:
     *  $prompt = 'Files '
     *  $input = $this->prompt($prompt)
     *      # Files _
     *      # Files open_
     *  $file = $this->promptFollowUp($prompt, $input, 'path')
     *      # Files open <path>: _
     * 
     *  $prompt = '~> git '
     *  $input = $this->prompt($prompt)
     *      # ~> git add
     *      # ~> git add_
     *  $path = $this->promptFollowUp($prompt, $input, 'path')
     *      # ~> git add <path>: _
     */
    public function promptFollowUp(string $prompt, $priorInput, string $placeholder, string $suffix = ': ', $default = null, array $rules = [], array $messages = [])
    {
        // Remove suffix from original prompt (user must do this if it differs from the new suffix provided)
        $prompt = rtrim(str_replace($suffix, '', $prompt));
        $prompt = sprintf('%s %s <%s>%s', $prompt, $priorInput, $placeholder, $suffix);

        return $this->prompt($prompt, $default, $rules, $messages);
    }

    // protected function handle(Event $Event)
    // {
    //     switch (get_class($Event)) {
    //         case Abort::class:
    //             $this->returnCode = $Event->code;
    //             $this->exit();
    //             break;
    //     }
    // }

    // public function route($input, array $params = [])
    // {
    //     $returnCode = 0;

    //     $this->Router->validate($input);
    //     $this->Route = $this->Router->getRoute($input);
    //     $return = $this->Router->route($input, $params);

    //     if (is_int($return)) {
    //         $returnCode = $return;
    //     }

    //     if ($returnCode !== 0) {
    //         throw new Abort($returnCode);
    //     }

    //     return $return;
    // }

    public function viewsPath(): string
    {
        return $this->viewsPath;
    }

    protected function setViewsPath(string $path)
    {
        $this->viewsPath = $path;
    }
}