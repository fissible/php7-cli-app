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

    private View $View;

    private Collection $Components;

    private array $content;

    public function __construct(ScreenApplication $App)
    {
        $this->Application = $App;
    }

    public function app(): ScreenApplication
    {
        return $this->Application;
    }

    public function component(string $name, int $x = 0, int $y = 0, int $width = 1, int $height = 1): Component
    {
        $Component = new Component($this->View, $name, $x, $y, $width, $height);

        $this->View->setComponent($Component);

        return $this->getComponent($name);
    }

    public function draw(bool $clear = false): void
    {
        if ($clear) {
            $this->Application->clear();
        } else {
            Cursor::put(0, 0);
        }

        // Print each line of the rendered View.
        if (isset($this->View)) {

            Cursor::hide();

            $lines = array_map(function ($row) {
                return implode(array_map(function ($char) {
                    if ($char === null) return ' ';
                    return $char;
                }, $row->toArray()));
            }, $this->View->render()->toArray());

            $this->Application->output->lines($lines);

            Cursor::put(0, 0);
            Cursor::show();
        }
    }

    /**
     * Get the specified Component.
     * 
     * @param string $name
     * @return Component|null
     */
    public function getComponent(string $name): ?Component
    {
        return $this->View->getComponent($name);
    }

    public function setContent($content): self
    {
        if ($content instanceof View) {
            $content = $content->render();
        }

        if (!is_array($content)) {
            throw new \InvalidArgumentException('Screen content must be a View instance or an array.');
        }

        $this->content = $content;

        return $this;
    }

    public function view(string $name = null, array $data = [], $config = null): View
    {
        if (!is_null($name) || !empty($data) || $config) {
            if (is_null($name)) {
                $File = $this->View->File();
            } else {
                $name = str_replace('.txt', '', $name);
                $File = new File($this->Application->viewsPath() . '/' . $name . '.txt');
            }

            if (empty($data) && isset($this->View)) {
                $data = $this->View->data();
            }

            if (is_null($config) && isset($this->View)) {
                $config = $this->View->config();
            }

            $this->View = new View($data, $File, $config);
        }

        return $this->View;
    }

    public function setView(View $View): self
    {
        $this->View = $View;

        return $this;
    }

    /**
     * Height of current console
     */
    public static function height()
    {
        return static::rows();
    }

    /**
     * Width of current console
     */
    public static function cols()
    {
        return Output::rtput('cols');
    }

    /**
     * Height of current console
     */
    public static function rows()
    {
        return Output::rtput('lines');
    }

    /**
     * Width of current console
     */
    public static function width()
    {
        return static::cols();
    }
}