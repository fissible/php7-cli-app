<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Collection;
use PhpCli\Grid;
use PhpCli\Str;
use PhpCli\Filesystem\File;
use PhpCli\Output;

class ViewTemplate extends File
{
    private Grid $Grid;

    private Collection $Components;

    public function __construct(string $path)
    {
        parent::__construct($path);

        $this->setGrid();
    }

    public function aspectRatio()
    {
        return $this->width() / $this->height();
    }

    /**
     * @return int
     */
    public function width(): int
    {
        return $this->Grid->width();
    }

    /**
     * @return int
     */
    public function height(): int
    {
        return $this->Grid->height();
    }

    public function getGrid(): Grid
    {
        return $this->Grid;
    }

    public function getComponents(): Collection
    {
        if (!isset($this->Components)) {
            $this->Components = new Collection();
            $headers = $this->getHeaders();
            $lines = $this->getLines();
            $componentNames = static::getComponentNames(implode("\n", $lines));

            foreach ($componentNames as $componentId) {
                [$startCoords, $stopCoords] = $this->getBoundingBox($componentId);
                $name = ltrim($componentId, '#');
                $styles = $headers['styles'][$name] ?? [];
                
                foreach ($headers as $key => $value) {
                    if ($key === 'styles') continue;
                    $styles[$key] = $value;
                }

                $x = $startCoords[1];
                $y = $startCoords[0];
                $width = ($stopCoords[1] + 1) - $startCoords[1];
                $height = ($stopCoords[0] + 1) - $startCoords[0];

                $this->Components->push(new Component($name, $x, $y, $width, $height, $styles));
            }
        }
        

        return $this->Components;
    }

    /**
     * Get and parse the template file headers.
     * 
     * @return array
     */
    public function getHeaders(array $defaults = []): array
    {
        $headers = $defaults;
        $capturing = null;
        $empty = 0;

        foreach ($this->lines() as $line) {
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

        foreach ($this->lines() as $line) {
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

    public function getVariables(): array
    {
        $variables = [];

        $string = $this->read();

        foreach (static::getVariableNames($string) as $name) {
            $variables[$name] = $this->getVariableCoords($name);
        }

        return $variables;
    }

    /**
     * Parse the template File and register components and variable names.
     * 
     * @return self
     */
    private function setGrid(): self
    {
        $lines = $this->getLines();
        $grid = [];

        if (empty($lines)) {
            throw new \Exception(sprintf('Template file \'%s\' empty.', $this->path));
        }

        foreach ($lines as $row) {
            $grid[] = array_map(function ($char) {
                if ($char === ' ') return null;
                return $char;
            }, Str::split($row));
        }

        $this->Grid = new Grid($grid);

        return $this;
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
        return $this->Grid->find('$' . $name);
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
        if ($coords = $this->Grid->find($componentId)) {
            $this->Grid->pointer(...$coords); // coords of "#"

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
            while (!in_array($this->Grid->peek(), $startChars)) {
                if ($foundEdge === 0 && $peek = $this->Grid->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->Grid->up();
                } else {
                    $this->Grid->left();
                }
            }
            $startCoords[] = $this->Grid->pointer();

            $this->Grid->pointer(...$coords);


            // Find start down, then left, then then up (because starting point is not centered in the component)
            $foundEdge = 0;
            while (!in_array($this->Grid->peek(), $startChars)) {
                if ($foundEdge === 0 && $peek = $this->Grid->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                } elseif ($foundEdge === 1 && $peek = $this->Grid->peek()) {
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
                    $this->Grid->down();
                } elseif ($foundEdge === 1) {
                    try {
                        $this->Grid->left();
                    } catch (\RangeException $e) {
                        print "\npeeked: " . $this->Grid->peek() . "\n";
                        throw $e;
                    }
                } elseif ($foundEdge === 2) {
                    $this->Grid->up();
                } else {
                    throw new \Exception('Found too many edges.');
                }
            }
            $startCoords[] = $this->Grid->pointer();

            if (empty($startCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box start delimiter.', $componentId));
            }

            $startCoords = [min($startCoords[0][0], $startCoords[1][0]), min($startCoords[0][1], $startCoords[1][1])];

            $this->Grid->pointer(...$coords);

            $stopCoords = [];

            // Find stop down then right
            $foundEdge = 0;
            while (!in_array($this->Grid->peek(), $stopChars)) {
                if (!$foundEdge && $peek = $this->Grid->peek()) {
                    if (Str::contains($peek, Output::uchar('hor', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->Grid->down();
                } else {
                    $this->Grid->right();
                }
            }
            $stopCoords[] = $this->Grid->pointer();

            $this->Grid->pointer(...$coords);

            // Find stop right then down
            $foundEdge = 0;
            while (!in_array($this->Grid->peek(), $stopChars)) {
                if (!$foundEdge && $peek = $this->Grid->peek()) {
                    if (Str::contains($peek, Output::uchar('ver', $variant, true))) {
                        $foundEdge = 1;
                    }
                }

                if ($foundEdge === 0) {
                    $this->Grid->right();
                } else {
                    $this->Grid->down();
                }
            }
            $stopCoords[] = $this->Grid->pointer();

            if (empty($stopCoords)) {
                throw new \Exception(sprintf('%s: Unable to locate bounding box stop delimiter.', $componentId));
            }

            $stopCoords = [max($stopCoords[0][0], $stopCoords[1][0]), max($stopCoords[0][1], $stopCoords[1][1])];
        } else {
            throw new \Exception(sprintf('%s: Unable to locate component identifier.', $componentId));
        }

        return [$startCoords, $stopCoords];
    }
}