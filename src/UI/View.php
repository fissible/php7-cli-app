<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Arr;
use PhpCli\Buffer;
use PhpCli\Interfaces\Config;
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

    public function __construct(array $data = [], File $File = null, $config = null)
    {
        $this->data = $data;

        if ($config instanceof \stdClass) {
            $this->defaults = array_merge($this->defaults, Arr::fromObject($config));
        } elseif ($config instanceof Config) {
            $this->setConfig($config);
        }

        if ($File) {
            $this->setFile($File);
        }
    }

    /**
     * Check if the Component has content.
     * 
     * @param string $name
     * @return bool
     */
    public function componentHasContent(string $name): bool
    {
        if ($this->hasComponent($name)) {
            return $this->getComponent($name)->hasContent();
        }
        return false;
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

    public function File(): File
    {
        return $this->File;
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

    /**
     * Get the specified Component.
     * 
     * @param string $name
     * @return Component|null
     */
    public function getComponent(string $name): ?Component
    {
        if ($this->hasComponent($name)) {
            return $this->Components->get($name);
        }

        return null;
    }

    /**
     * Check if the specified Component exists.
     * 
     * @param string $name
     * @return bool
     */
    public function hasComponent(string $name): bool
    {
        if (isset($this->Components)) {
            return $this->Components->has($name);
        }
        
        return false;
    }

    /**
     * Add a Component to the View.
     * 
     * @param string $name
     * @param Component $Component
     */
    public function setComponent(Component $Component): self
    {
        $this->Components->set($Component->name, $Component);

        return $this;
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

    private function replaceComponents()
    {
        $variant = $this->borderVariant();
        $borderChars = [
            'hor' => Output::uchar('hor', $variant),
            'ver' => Output::uchar('ver', $variant),
            'down_right' => Output::uchar('down_right', $variant),
            'down_left' => Output::uchar('down_left', $variant),
            'up_right' => Output::uchar('up_right', $variant),
            'up_left' => Output::uchar('up_left', $variant),
            'ver_right' => Output::uchar('ver_right', $variant),
            'ver_left' => Output::uchar('ver_left', $variant),
            'down_hor' => Output::uchar('down_hor', $variant),
            'up_hor' => Output::uchar('up_hor', $variant),
            'cross' => Output::uchar('cross', $variant)
        ];

        $this->Components->each(function (Component $Component) use ($variant, $borderChars) {
            // Erase the Component ID string
            $componentId = '#'.$Component->name;
            if ($coords = $this->view->find($componentId)) {
                $chars = Str::split($componentId);
                foreach ($chars as $x => $char) {
                    if ($this->view->valid($coords[0], $coords[1] + $x)) {
                        $this->view->set($coords[0], $coords[1] + $x, ' ');
                    }
                }
            }

            // Map the content into the view
            $lines = $Component->render();

            foreach ($lines as $y => $line) {
                $chars = Str::split($line);
                
                foreach ($chars as $x => $char) {
                    $newY = $Component->y + $y;
                    $newX = $Component->x + $x;

                    if ($this->view->valid($newY, $newX)) {
                        $currChar = $this->view->get($newY, $newX);
                        // check for intersecting borders
                        if (in_array($char, array_values($borderChars))) {
                            if (in_array($currChar, array_values($borderChars))) {
                                $char = Output::combine_lines($currChar, $char, $variant);
                            }
                        } elseif (!empty($currChar) && empty($char)) {
                            continue;
                        }
                        $this->view->set($newY, $newX, $char);
                    } else {
                        throw new \Exception(sprintf('y: %d x: %d, invalid coordinates', $newY, $newX));
                    }
                }
            }
        });
    }

    /**
     * Scan the template files lines for occurences of configured data
     * variables and replace them with their values.
     * 
     * @return array
     */
    private function replaceVariables()
    {
        foreach ($this->variables as $key => $coords) {
            $value = '';
            if (isset($this->data[$key])) {
                $value = $this->data[$key];
            }

            if (!is_string($value)) {
                if (!is_scalar($value)) {
                    $value = gettype($value);
                } else {
                    $value = $value . '';
                }
            }

            // Replace the "$variable" string with the value in the view
            $lines = explode("\n", $value);
            foreach ($lines as $y => $line) {
                $chars = Str::split($line);
                foreach ($chars as $x => $char) {
                    // if ($char === ' ') $char = null;
                    if ($this->view->valid($coords[0] + $y, $coords[1] + $x)) {
                        $this->view->set($coords[0] + $y, $coords[1] + $x, $char);
                    }
                }
            }
        }
    }

    /**
     * Replace the section IDs and variables in the template with the configured
     * data and return the result as an array of lines.
     * 
     * @return array
     */
    public function render()
    {
        // blank out all content
        $this->view = Grid::create($this->view->width(), $this->view->height());

        $this->replaceComponents();
        $this->replaceVariables();

        return $this->view;
    }

    /**
     * @return int
     */
    public function width(): int
    {
        return $this->view->width();
    }

    /**
     * @return int
     */
    public function height(): int
    {
        return $this->view->height();
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

    private function getVariableCoords(string $name): array
    {
        return $this->view->find('$' . $name);
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
        $variant = 'light'; // Templates must use the light variant @todo consider using simple (non-unicode) instead

        // find "#..." string coords
        if ($coords = $this->view->find($componentId)) {
            $this->view->pointer(...$coords); // coords of "#"

            $startChars = [
                Output::uchar('down_right', $variant, true), // ┌
                Output::uchar('down_hor', $variant, true),   // ┬
                Output::uchar('ver_right', $variant, true),  // ├
                Output::uchar('cross', $variant, true)       // ┼
            ];

            $stopChars = [
                Output::uchar('up_hor', $variant, true),     // ┴
                Output::uchar('ver_left', $variant, true),   // ┤
                Output::uchar('up_left', $variant, true),    // ┘
                Output::uchar('cross', $variant, true)       // ┼
            ];

            $startCoords = [];

            // Find start up then left
            $foundEdge = 0;
            while (!in_array($this->view->peek(), $startChars)) {
                if ($foundEdge === 0 && $peek = $this->view->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->view->up();
                } else {
                    $this->view->left();
                }
            }
            $startCoords[] = $this->view->pointer();

            $this->view->pointer(...$coords);


            // Find start down, then left, then then up (because starting point is not centered in the component)
            $foundEdge = 0;
            while (!in_array($this->view->peek(), $startChars)) {
                if ($foundEdge === 0 && $peek = $this->view->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                } elseif ($foundEdge === 1 && $peek = $this->view->peek()) {
                    $canGoUp = [
                        Output::uchar('up_right', $variant, true),
                        Output::uchar('ver_right', $variant, true),
                        Output::uchar('up_hor', $variant, true),
                        Output::uchar('cross', $variant, true)
                    ];
                    foreach ($canGoUp as $char) {
                        if (Str::contains($peek, $char)) {
                            $foundEdge = 2;
                            break;
                        }
                    }
                }

                if ($foundEdge === 0) {
                    $this->view->down();
                } elseif ($foundEdge === 1) {
                    try {
                        $this->view->left();
                    } catch (\RangeException $e) {
                        print "\npeeked: ".$this->view->peek()."\n";
                        throw $e;
                    }
                } elseif ($foundEdge === 2) {
                    $this->view->up();
                } else {
                    throw new \Exception('Found too many edges.');
                }
            }
            $startCoords[] = $this->view->pointer();

            if (empty($startCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box start delimiter.', $componentId));
            }

            $startCoords = [min($startCoords[0][0], $startCoords[1][0]), min($startCoords[0][1], $startCoords[1][1])];

            $this->view->pointer(...$coords);

            $stopCoords = [];

            // Find stop down then right
            $foundEdge = 0;
            while (!in_array($this->view->peek(), $stopChars)) {
                if (!$foundEdge && $peek = $this->view->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->view->down();
                } else {
                    $this->view->right();
                }
            }
            $stopCoords[] = $this->view->pointer();

            $this->view->pointer(...$coords);

            // Find stop right then down
            $foundEdge = 0;
            while (!in_array($this->view->peek(), $stopChars)) {
                if (!$foundEdge && $peek = $this->view->peek()) {
                    if (Str::contains($peek, Output::uchar('ver', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->view->right();
                } else {
                    $this->view->down();
                }
            }
            $stopCoords[] = $this->view->pointer();

            if (empty($stopCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box stop delimiter.', $componentId));
            }

            $stopCoords = [max($stopCoords[0][0], $stopCoords[1][0]), max($stopCoords[0][1], $stopCoords[1][1])];
        } else {
            throw new \Exception(sprintf('%s: Unable to locate component identifier.', $componentId));
        }

        return [$startCoords, $stopCoords];
    }

    private function borderVariant(): string
    {
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
        $string = '';
        $grid = [];

        foreach ($lines as $row) {
            $grid[] = array_map(function ($char) {
                if ($char === ' ') return null;
                return $char;
            }, Str::split($row));
        }

        $this->view = new Grid($grid);
        $this->variables = [];

        unset($grid);
        $string = implode("\n", $lines);
        unset($lines);
        $variables = static::getVariableNames($string);
        foreach ($variables as $name) {
            $this->variables[$name] = $this->getVariableCoords($name);
        }

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
            } elseif ($componentId === 'cursor') {
                $Component->setContent('');
            }

            $this->setComponent($Component);
        }

        return $this;
    }

    public function __get($name)
    {
        if ($name === 'config') {
            return $this->Config;
        }

        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        throw new \Exception(sprintf("%s: unknown property", $name));
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
}