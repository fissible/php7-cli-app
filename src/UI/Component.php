<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Arr;
use PhpCli\Interfaces\Config;
use PhpCli\Str;
use PhpCli\Output;
use PhpCli\Traits\HasConfig;

class Component
{
    use HasConfig;

    private string $name;

    private int $height;

    private int $width;

    private int $x;

    private int $y;

    private $content;

    private array $defaults = [
        'overflow' => 'hidden' // hidden, scroll, nowrap
    ];

    private $formattedLines;

    private array $offset = [
        'y' => 0,
        'x' => 0
    ];

    // private View $View;

    public function __construct(View $View, string $name, int $x = 0, int $y = 0, int $width = 1, int $height = 1)
    {
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
        $this->setWidth($width);
        $this->setHeight($height);
        $this->setConfig($this->getStyles($View->config()));
    }

    public function colorizeContent(array $lines): array
    {
        if ($this->config()->has('color')) {
            $formattedLines = $this->getFormattedContentLines();

            foreach ($formattedLines as $key => $contentLine) {
                foreach ($lines as $y => $line) {
                    if (Str::contains($line, $contentLine)) {
                        //           mb_replace($search, $replace, $subject, &$count = 0)
                        $lines[$y] = mb_replace($contentLine, Output::color($contentLine, $this->config()->color), $line);
                    }
                }
            }
        }

        // return array of ['find' => 'replace'] or just colorize the provided lines array
        return $lines;
    }

    public function getCoordinates(string $startStop = null): array
    {
        $startCoords = [$this->y, $this->x];

        if ($startStop === 'start') {
            return $startCoords;
        }

        $stopCoords = [$this->y + $this->height, $this->x + $this->width];

        if ($startStop === 'stop') {
            return $stopCoords;
        }

        return [$startCoords, $stopCoords];
    }

    public function getContent()
    {
        $content = $this->content;

        if ($content instanceof Component) {
            $content = implode("\n", $content->render());
        }

        return $content;
    }

    /**
     * Format the 
     */
    public function getFormattedContentLines()
    {
        if (!isset($this->formattedLines)) {
            $content = $this->content;
            $width = $this->getWidth();
            $height = $this->getHeight();
            $widest = 0;

            if ($content instanceof Component) {
                $content = implode("\n", $content->render());
            }

            // hidden, scroll (y), nowrap (scroll x+y)
            // Apply word wrapping (inserts "\n" characters)
            if ($this->config()->has('overflow') && in_array($this->config()->overflow, ['hidden', 'scroll'])) {
                $content = wordwrap($content, $width, "\n", true);
            }

            $lines = explode("\n", $content);

            // Justify the content
            if ($this->config()->has('align')) {
                $lines = array_map(function (string $row) use ($width) {
                    switch (strtolower($this->config()->align)) {
                        default:
                        case 'left':
                            $pad_type = STR_PAD_RIGHT;
                            break;
                        case    'right':
                            $pad_type = STR_PAD_LEFT;
                            break;
                        case  'center':
                            $pad_type = STR_PAD_BOTH;
                            break;
                    }
                    return str_pad($row, $width, ' ', $pad_type);
                }, $lines);
            }

            // debug
            // $lines = array_map(function (string $row) {
            //     return substr_replace(substr_replace($row, '|', 0, 1), '|', -1, 1);
            // }, $lines);


            // Trim columns to height
            if (count($lines) > $height) {
                $lines = array_slice($lines, $this->offset['y'], $height);
            }

            // get the longest row
            foreach ($lines as $row) {
                if (($len = mb_strlen($row)) > $widest) {
                    $widest = $len;
                }
            }

            // hidden, scroll (y), nowrap (scroll x+y)
            // Trim rows to width
            if ($widest > $width) {
                $lines = array_map(function (string $row) use ($width) {
                    if (Str::length($row) > $width) {
                        return mb_substr($row, $this->offset['x'], $width);
                    }
                    return $row;
                }, $lines);
            }

            $this->formattedLines = $lines;
        }

        return $this->formattedLines;
    }

    /**
     * Get the Component (scroll) offset.
     * 
     * @param string|null $key
     * @return array|int
     */
    public function getOffset(string $key = null)
    {
        if ($key === null) {
            return $this->offset;
        }

        if (isset($this->offset[$key])) {
            return $this->offset[$key];
        }

        throw new \InvalidArgumentException('Offset key must be "x" or "y".');
    }

    public function render(): array
    {
        $padding = $this->getPadding();
        $vpadding = $this->getVerticalPadding();
        $pad = str_repeat(' ', $padding);
        
        // Convert content string into array of strings
        $lines = $this->getFormattedContentLines();

        // add horizontal padding to each row
        $lines = array_map(function (string $row) use ($pad) {
            return $pad . $row . $pad;
        }, $lines);

        // print "\n" . $this->name . " + lines[] 4:\n";
        // var_export($lines);

        // add vertical padding to rows
        if ($vpadding > 0) {
            // add padding rows: rows of spaces equal to longest row
            for ($p = 0; $p < $vpadding; $p++) {
                array_unshift($lines, str_repeat(' ', $this->width));
                array_push($lines, str_repeat(' ', $this->width));
            }
        }

        return $lines;
    }

    public function setContent($content)
    {
        if (!is_string($content) && !($content instanceof Component)) {
            throw new \InvalidArgumentException('Component content must be a string or a Component instance, "%s" given.', gettype($content));
        }

        $this->content = $content;
    }

    public function setHeight(int $height): self
    {
        $this->height = max($height, 3);

        return $this;
    }

    /**
     * Set the Component (scroll) offset.
     * 
     * @param int $y
     * @param int $x
     * @return self
     */
    public function setOffset(int $y, int $x): self
    {
        $this->offset['y'] = $y;
        $this->offset['x'] = $x;

        return $this;
    }

    public function setWidth(int $width): self
    {
        $this->width = max($width, 3);

        return $this;
    }

    public function __get(string $name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
        return null;
    }

    public function __isset(string $name)
    {
        return isset($this->$name);
    }

    private function getStyles(Config $viewConfig): \stdClass
    {
        $config = Arr::toObject($this->defaults);
        $thisStylesKey = sprintf('styles.%s', $this->name);

        if ($viewConfig->has($thisStylesKey)) {
            $config = (object) array_merge((array) $config, (array) $viewConfig->get($thisStylesKey));

            if (!isset($config->padding) && $viewConfig->has('padding')) {
                $config->padding = intval($viewConfig->get('padding'));
            }

            if (!isset($config->border) && $viewConfig->has('border')) {
                $config->border = $viewConfig->border;
            }
        }

        return $config;
    }

    private function getBorder(): int
    {
        $border = 1;
        if (isset($this->config()->border)) {
            if (filter_var($this->config()->border, FILTER_VALIDATE_INT|FILTER_VALIDATE_BOOLEAN)) {
                $border = $this->config()->border ? 1 : 0;
            } elseif ($this->config()->border === 'none') {
                $border = 0;
            }
        }

        return $border;
    }

    private function getPadding(): int
    {
        return intval($this->config()->padding ?? 0);
    }

    private function getVerticalPadding(): int
    {
        $vpadding = 0;
        if ($padding = $this->getPadding()) {
            $vpadding = intval($padding / 2);
        }

        return $vpadding;
    }

    private function getHeight(): int
    {
        $height = $this->height;
        $borders = $this->getBorder();
        $vpadding = $this->getVerticalPadding();
        $height -= ($vpadding * 2) + ($borders * 2);

        return $height;
    }

    private function getWidth(): int
    {
        $width = $this->width;
        $padding = $this->getPadding();
        $borders = $this->getBorder();
        $width -= ($padding * 2) + ($borders * 2);

        return $width;
    }
}