<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Application;
use PhpCli\Output;
use PhpCli\Traits\HasConfig;

class Screen
{
    use HasConfig;

    private Application $App;

    private array $content;

    public function __construct(Application $App)
    {
        $this->App = $App;
    }

    public function draw(): void
    {
        $this->App->clear();
        if (isset($this->content)) {
            foreach ($this->content as $y => $row) {
                $this->App->output->line($row);
            }
        }
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