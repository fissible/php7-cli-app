<?php declare(strict_types=1);

namespace PhpCli\UI;

use Ds\Stack;
use PhpCli\Collection;
use PhpCli\Grid;
use PhpCli\Filesystem\File;
use PhpCli\Output;
use PhpCli\Str;
use PhpCli\Traits\HasConfig;

class View
{
    use HasConfig;

    private File $File;

    private array $components = [];
    private Collection $Components;

    private array $data;

    private array $defaults = [
        'border-style' => 'light',
        'padding' => 1,
        'styles' => []
    ];

    private array $offset = [
        'y' => 0,
        'x' => 0
    ];

    private array $variables = [];

    private Grid $view;

    public function __construct(array $data = [], File $File = null, array $config = [])
    {
        $this->data = $data;

        if (!empty($config)) {
            $this->defaults = array_merge($this->defaults, $config);
        }

        if ($File) {
            $this->setFile($File);
        }
    }

    /**
     * Get the view data.
     * 
     * @return array
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get and parse the template file headers.
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        $headers = $this->defaults;
        $capturing = null;
        $empty = 0;

        foreach ($this->File->lines() as $line) {
            if (empty($line)) {
                $empty++;
                continue;
            }

            if ($empty < 2) {
                if (Str::contains($line, ':')) {
                    list($hkey, $hvalue) = array_map('trim', explode(':', $line, 2));

                    if ($capturing) {
                        $headers['styles'][$capturing][$hkey] = $hvalue;
                    } else {
                        $headers[$hkey] = $hvalue;
                    }
                } elseif (Str::contains($line, '{')) {
                    $capturing = trim(Str::before($line, '{'), "#\t ");
                    $headers['styles'][$capturing] = [];
                } elseif (Str::contains($line, '{')) {
                    $capturing = null;
                }
            } else {
                break;
            }
        }

        return $headers;
    }

    /**
     * Get the template file markup as an array of lines.
     * 
     * @return array
     */
    public function getLines(): array
    {
        $lines = [];
        $empty = 0;
        
        foreach ($this->File->lines() as $line) {
            if (empty($line)) {
                $empty++;
                continue;
            }

            if ($empty < 2) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    public function hasComponent(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Set the template file.
     * 
     * @param File $File
     * @return self
     */
    public function setFile(File $File): self
    {
        $this->File = $File;
        $this->setConfig($this->getHeaders());
        $this->setGrid();

        return $this;
    }

    private function replaceComponents(array $lines)
    {
        $this->Components->each(function (Component $Component) use (&$lines) {
            $x = $Component->x;
            foreach ($Component->render() as $contentIndex => $content) {
                $y = $Component->y + $contentIndex + 1;

                if (!isset($lines[$y])) {
                    throw new \RangeException(sprintf('%d: row offset does not exist in the File lines', $y));
                }

                $line = &$lines[$y];

                if (mb_strlen($line) <= $x) {
                    throw new \RangeException(sprintf('%d: column offset does not exist in File line', $x));
                }
                
                if ($contentIndex === 0) {
                    $search = '#' . $Component->name;

                    $pos = mb_strpos($line, $search);
                    if ($pos !== false) {
                        $line = mb_replace($search, str_repeat(' ', strlen($search)), $line);
                    }
                }

                $content_length = Str::length($content);
                $line_length = mb_strlen($line);
                $pos = $x + 1;
                $line = mb_substr($line, 0, $pos) . $content . mb_substr($line, $content_length + $pos, $line_length - $content_length + $pos);
            }
        });

        return $lines;
    }

    /**
     * Scan the template files lines for occurences of configured data
     * variables and replace them with their values.
     * 
     * @param array $lines
     * @return array
     */
    private function replaceVariables(array $lines)
    {
        foreach ($this->variables as $key) {
            if (!isset($this->data[$key])) {
                continue;
            }

            $value = $this->data[$key];

            if (!is_string($value)) {
                if (!is_scalar($value)) {
                    $value = gettype($value);
                } else {
                    $value = $value . '';
                }
            }

            $lines = array_map(function ($line) use ($key, $value) {
                if (Str::contains($line, $key)) {
                    $search = str_pad('$' . $key, strlen($value));
                    $value = str_pad($value, strlen($search));
                    return mb_replace($search, $value, $line);
                }
                return $line;
            }, $lines);
        }

        return $lines;
    }

    /**
     * Replace the section IDs and variables in the template with the configured
     * data and return the result as an array of lines.
     * 
     * @return array
     */
    public function render(): array
    {
        $variant = $this->borderVariant();
        $borderChar = Output::uchar('ver', $variant, true);

        $lines = $this->getLines();
        $lines = $this->replaceComponents($lines);
        $lines = $this->replaceVariables($lines);

        // color the borders
        if ($this->Config->has('border-color')) {
            $topLeft = Output::uchar('down_right', $variant, true);
            $midLeft = Output::uchar('ver_right', $variant, true);
            $botLeft = Output::uchar('up_right', $variant, true);

            foreach ($lines as $y => $line) {
                $char = mb_substr($line, 0, 1) ?? null;

                switch ($char) {
                    case $topLeft:
                    case $midLeft:
                    case $botLeft:
                        $lines[$y] = Output::color($line, $this->Config->get('border-color'));
                        break;
                    case $borderChar:
                        $borderCharColor = Output::color($borderChar, $this->Config->get('border-color'));
                        $lines[$y] = mb_replace($borderChar, $borderCharColor, $line);
                        break;
                }
            }
        }

        // Color the component contents
        $this->Components->each(function (Component $Component) use (&$lines) {
            $lines = $Component->colorizeContent($lines);
        });

        return $lines;
    }


    /**
     * Scan the string for components ID strings, eg. "#footer"
     * 
     * @param string $string
     * @return array
     */
    private static function getComponentNames(string $string): array
    {
        $pattern = '/\#([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match_all($pattern, $string, $components);

        return $components[0] ?? [];
    }

    /**
     * Scan the string for "variables", string prefixed with "$", eg. "$username"
     * 
     * @param string $string
     * @return array
     */
    private static function getVariableNames(string $string): array
    {
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match_all($pattern, $string, $variables);

        return $variables[1] ?? [];
    }

    /**
     * For a given Component id (eg. "#header"), find
     * 
     * @param string $componentId
     * @return array
     */
    private function getBoundingBox(string $componentId): array
    {
        $startCoords = [];
        $stopCoords = [];
        $variant = $this->borderVariant();

        // Output::printIndexedArray($view);

        // find "#..." string coords
        if ($coords = $this->view->find($componentId)) {
            $this->view->pointer(...$coords); // coords of "#"

            $startChars = [
                Output::uchar('down_right', $variant, true),
                Output::uchar('down_hor', $variant, true),
                Output::uchar('ver_right', $variant, true),
                Output::uchar('cross', $variant, true)
            ];

            $foundEdge = false;
            while (!in_array($this->view->peek(), $startChars)) {
                if (!$foundEdge) {
                    $foundEdge = ($this->view->peek() === Output::uchar('ver', $variant, true));
                }

                if ($foundEdge) {
                    $this->view->up();
                } else {
                    $this->view->left();
                }
            }
            $startCoords = $this->view->pointer();

            if (empty($startCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box start delimiter.', $componentId));
            }

            $this->view->pointer(...$coords);

            $stopChars = [
                Output::uchar('up_hor', $variant, true),
                Output::uchar('ver_left', $variant, true),
                Output::uchar('up_left', $variant, true),
                Output::uchar('cross', $variant, true)
            ];

            $foundEdge = false;
            while (!in_array($this->view->peek(), $stopChars)) {
                if (!$foundEdge) {
                    $foundEdge = ($this->view->peek() === Output::uchar('ver', $variant, true));
                }

                if ($foundEdge) {
                    $this->view->down();
                } else {
                    $this->view->right();
                }
            }
            $stopCoords = $this->view->pointer();

            if (empty($stopCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box stop delimiter.', $componentId));
            }
        } else {
            throw new \Exception(sprintf('%s: Unable to locate component identifier.', $componentId));
        }

        return [$startCoords, $stopCoords];
    }

    private function borderVariant(): string
    {
        // @todo - auto-detect variant from File data
        return $this->Config->get('border-style') ?? 'light';
    }

    /**
     * Parse the template File and register components and variable names.
     * 
     * @return self
     */
    private function setGrid(): self
    {
        $this->Components = new Collection();
        $lines = $this->getLines();
        $string = implode("\n", $lines);
        $grid = [];

        foreach ($lines as $row) {
            $grid[] = mb_str_split($row);
        }

        $this->view = new Grid($grid);
        $this->variables = static::getVariableNames($string);

        foreach (static::getComponentNames($string) as $componentId) {
            [$startCoords, $stopCoords] = $this->getBoundingBox($componentId);
            $name = ltrim($componentId, '#');
            $x = $startCoords[1];
            $y = $startCoords[0];
            $width = ($stopCoords[1] + 1) - $startCoords[1];
            $height = ($stopCoords[0] + 1) - $startCoords[0];
            $Component = new Component($this, $name, $x, $y, $width, $height);

            if (isset($this->data[$componentId])) {
                $Component->setContent($this->data[$componentId]);
            }

            $this->Components->set($name, $Component);
        }

        return $this;
    }
}