<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Collection;
use PhpCli\Cursor;
use PhpCli\Filesystem\File;
use PhpCli\Output;
use PhpCli\ScreenApplication;
use PhpCli\Traits\HasConfig;
use PhpCli\UI\View;

class Screen
{
    use HasConfig;

    private ScreenApplication $Application;

    protected Collection $Views;

    private Collection $Components;

    private array $variables;

    private View $View;

    private array $content;

    public function __construct(ScreenApplication $App)
    {
        $this->Application = $App;
    }

    public function app(): ScreenApplication
    {
        return $this->Application;
    }

    /**
     * Check if the Component has content.
     * 
     * @param string $name
     * @return bool
     */
    public function componentHasContent(string $name): bool
    {
        if ($this->hasComponent($name, $this->height(), $this->width())) {
            return $this->getComponent($name, $this->height(), $this->width())->hasContent();
        }
        return false;
    }

    public function draw(bool $clear = false): void
    {
        if ($clear) {
            $this->Application->clear();
        } else {
            Cursor::put(0, 0);
        }

        // Print each line of the rendered View.
        $View = $this->getView();
        Cursor::hide();

        $lines = array_map(function ($row) {
            return implode(array_map(function ($char) {
                if ($char === null) return ' ';
                return $char;
            }, $row));
        }, $View->render($this->height(), $this->width())->toArray());

        $this->Application->output->lines($lines);

        Cursor::put(0, 0);
        Cursor::show();
    }

    /**
     * Get the specified Component.
     * 
     * @param string $name
     * @return Component|null
     */
    public function getComponent(string $name): ?Component
    {
        return $this->getView()->getComponent($name, $this->height(), $this->width());
    }

    public function hasComponent(string $name): bool
    {
        return $this->getView()->hasComponent($name, $this->height(), $this->width());
    }

    public function getView(): View
    {
        return $this->View;
    }

    // public function makeComponent(string $name, int $x = 0, int $y = 0, int $width = 1, int $height = 1): Component
    // {
    //     $Component = new Component($name, $x, $y, $width, $height);

    //     $this->View->setComponent($Component);

    //     return $this->getComponent($name);
    // }

    public function makeView(string $viewName, array $templateNames = [], array $data = [], $config = null): View
    {
        $Files = [];
        $name = null;

        foreach ($templateNames as $name) {
            $name = str_replace('.txt', '', $name);
            $File = new ViewTemplate($this->Application->viewsPath() . '/' . $name . '.txt');

            if (!$File->exists()) {
                throw new \InvalidArgumentException(sprintf('ViewTemplate does not exist at path \'%s\'', $File->path));
            }

            $Files[] = $File;
        }

        $View = new View($viewName, $data, $Files, $config);

        return $View;
    }

    public function setContent($content): self
    {
        if ($content instanceof View) {
            $content = $content->render($this->height(), $this->width());
        }

        if (!is_array($content)) {
            throw new \InvalidArgumentException('Screen content must be a View instance or an array.');
        }

        $this->content = $content;

        return $this;
    }

    public function setData(string $key, $value)
    {
        $this->getView()->$key = $value;
    }

    public function setView(View $View): self
    {
        $this->View = $View;

        $this->Components = $View->getComponents();

        return $this;
    }

    /**
     * Height of current console
     */
    public static function height(): int
    {
        return static::rows();
    }

    /**
     * Width of current console
     */
    public static function cols(): int
    {
        return (int) Output::rtput('cols')[0];
    }

    /**
     * Height of current console
     */
    public static function rows(): int
    {
        return (int) Output::rtput('lines')[0];
    }

    /**
     * Width of current console
     */
    public static function width(): int
    {
        return static::cols();
    }
}