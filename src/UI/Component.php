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
                if (empty($contentLine)) continue;
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

    public function getBorder(): int
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

    /**
     * Get the content coordinates.
     * 
     * @return array
     */
    public function getContentCoords(string $startStop = 'start')
    {
        $borders = $this->getBorder();
        $padding = $this->getPadding();
        $vpadding = $this->getVerticalPadding();
        [$y, $x] = $this->getCoordinates('start');

        $x += $borders;
        $x += $padding;

        if ($startStop === 'stop') {

            $formattedLines = $this->getFormattedContentLines();
            $lineCount = count($formattedLines);

            foreach ($formattedLines as $key => $contentLine) {
                $y++;
                if ($key === $lineCount) {
                    $x += Str::length($contentLine);
                    break;
                }
            }
        } else {
            $y += $vpadding;
            $y += $borders;
        }

        return [$y, $x];
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
    public function getFormattedContentLines(bool $color = false)
    {
        if (!isset($this->formattedLines)) {
            $content = $this->content ?? '';
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
            $lines = array_map(function ($input) {
                return preg_replace('/\t/', '    ', $input);
            }, $lines);

            // print_r(array_map(function ($line) {
            //     return $line.' - ('.strlen($line).'|'.mb_strlen($line).'|'.Str::length($line).')';
            // }, $lines));

            if (!$color) {
                $lines = array_map(function ($input) { return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $input); }, $lines);
            }

            // print_r(array_map(function ($line) {
            //     return $line . ' - (' . strlen($line) . '|' . mb_strlen($line) . '|' . Str::length($line) . ')';
            // }, $lines));

            // Justify the content
            if ($this->config()->has('align')) {
                $lines = array_map(function (string $row) use ($width) {
                    switch (strtolower($this->config()->align)) {
                        default:
                        case 'left':
                            $pad_type = STR_PAD_RIGHT;
                            break;
                        case 'right':
                            $pad_type = STR_PAD_LEFT;
                            break;
                        case 'center':
                            $pad_type = STR_PAD_BOTH;
                            break;
                    }
                    return Str::pad($row, $width, ' ', $pad_type);
                }, $lines);
            } else {
                $lines = array_map(function (string $row) use ($width) {
                    return Str::pad($row, $width);
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
                if (($len = Str::length($row)) > $widest) {
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

    public function getPadding(): int
    {
        return intval($this->config()->padding ?? 0);
    }

    public function getVerticalPadding(): int
    {
        $vpadding = 0;
        if ($padding = $this->getPadding()) {
            $vpadding = intval($padding / 2);
        }

        return $vpadding;
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

    public function name(): string
    {
        return $this->name;
    }

    public function render(bool $colorize = false): array
    {
        $border = $this->getBorder();
        $padding = $this->getPadding();
        $vpadding = $this->getVerticalPadding();
        $height = $this->getHeight();
        $width = $this->getWidth();
        $pad = str_repeat(' ', $padding);

        // Convert content string into array of strings
        $lines = $this->getFormattedContentLines($colorize);

        // add horizontal padding to each row
        $lines = array_map(function (string $row) use ($pad) {
            return $pad . $row . $pad;
        }, $lines);

        // add vertical padding to rows
        if ($vpadding > 0) {
            // add padding rows: rows of spaces equal to longest row
            for ($p = 0; $p < $vpadding; $p++) {
                array_unshift($lines, '');
                array_push($lines, '');
            }
        }

        // colorize content
        if ($colorize) {
            foreach ($lines as $key => $contentLine) {
                if (empty($contentLine)) continue;
                $lines[$key] = Output::color($contentLine, $this->config()->color);
            }
        }

        if ($border) {
            $borderStyle = $this->config()->get('border-style');
            $verticalBorder = Output::uchar('ver', $borderStyle);

            $topBorder = Output::uchar('down_right', $borderStyle);
            $topBorder .= str_repeat(Output::uchar('hor', $borderStyle), $this->width - ($border * 2));
            $topBorder .= Output::uchar('down_left', $borderStyle);

            array_unshift($lines, $topBorder);

            for ($i = 1; $i <= $height; $i++) {
                if (isset($lines[$i])) {
                    $lines[$i] = $verticalBorder . $lines[$i] . $verticalBorder;
                } else {
                    $lines[] = $verticalBorder . str_repeat(' ', $width + ($padding * 2)) . $verticalBorder;
                }
            }

            $bottomBorder = Output::uchar('up_right', $borderStyle);
            $bottomBorder .= str_repeat(Output::uchar('hor', $borderStyle), $this->width - ($border * 2));
            $bottomBorder .= Output::uchar('up_left', $borderStyle);
            array_push($lines, $bottomBorder);
        }

        return $lines;
    }

    public function setContent($content = '')
    {
        if (!is_string($content) && !($content instanceof Component)) {
            throw new \InvalidArgumentException(sprintf('Component content must be a string or a Component instance, "%s" given.', gettype($content)));
        }

        $this->formattedLines = null;
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
        $thisStylesKey = sprintf('styles.%s', $this->name);

        if ($viewConfig->has($thisStylesKey)) {
            $config = $viewConfig->get($thisStylesKey);
        } else {
            $config = Arr::toObject($this->defaults);
        }

        foreach ($viewConfig->getData() as $key => $value) {
            if ($key === 'styles') continue;
            $config->$key = $value;
        }

        foreach ($this->defaults as $key => $value) {
            if (!isset($config->$key)) {
                $config->$key = $value;
            }
        }

        if (!isset($config->padding) && $viewConfig->has('padding')) {
            $config->padding = intval($viewConfig->get('padding'));
        }

        if (!isset($config->border) && $viewConfig->has('border')) {
            $config->border = $viewConfig->border;
        }

        return $config;
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
        $width -= ($padding * 2);
        $width -= ($borders * 2);

        return $width;
    }
}