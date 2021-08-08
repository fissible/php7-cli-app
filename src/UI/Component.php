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

    public static $colorize = true;

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

    private array $offsetMax = [
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

    public function appendContent(string $content, bool $newline = true)
    {
        if ($newline) {
            $content .= "\n";
        }

        $this->setContent(($this->content ?? '') . $content);
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
            $border = $this->config()->border;
            if ($border === 'none') {
                $border = 0;
            } elseif (is_numeric($border)) {
                $border = boolval($border) ? 1 : 0;
            } else {
                $border = filter_var($border, FILTER_VALIDATE_BOOLEAN);
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
    public function getFormattedContentLines(bool $refresh = false)
    {
        if (!isset($this->formattedLines) || $refresh) {
            $content = $this->content ?? '';
            $width = $this->getWidth();
            $height = $this->getHeight();
            $padding = $this->getPadding();
            $vpadding = $this->getVerticalPadding();
            $widest = 0;

            if ($content instanceof Component) {
                $content = implode("\n", $content->render());
            }

            // hidden, scroll (y), nowrap (scroll x+y)
            // Apply word wrapping (inserts "\n" characters)
            if ($this->config()->has('overflow') && in_array($this->config()->overflow, ['hidden', 'scroll'])) {
                $content = wordwrap($content, $width, "\n", true);
            }

            // Convert content string into array of lines
            $lines = explode("\n", $content);

            // Replace TABs with 4 spaces for consistency
            $lines = array_map(function ($input) {
                return preg_replace('/\t/', '    ', $input);
            }, $lines);

            // Strip control characters if color is disabled
            if (!static::$colorize) {
                $lines = array_map(function ($input) { return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $input); }, $lines);
            }

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

            // Calculate the max offsets
            $this->offsetMax['y'] = max(0, count($lines) - $height);
            $this->offsetMax['x'] = 0;

            foreach ($lines as $line) {
                if (($len = Str::length($line)) > $widest) {
                    $widest = $len;
                }

                $max = max(0, $len - $width);
                if ($max > $this->offsetMax['x']) {
                    $this->offsetMax['x'] = $max;
                }
            }

            // Reduce current offsets if they exceed maximums
            $this->offset['y'] = min($this->offset['y'], $this->offsetMax['y']);
            $this->offset['x'] = min($this->offset['x'], $this->offsetMax['x']);

            // Trim columns to height
            $trimTo = $height - ($vpadding);
            if (count($lines) > $height) {
                $lines = array_slice($lines, $this->offset['y'], /*$height */ $trimTo);
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
            $vpadding = intval($padding / 2.467);
            if ($padding > 1 && $vpadding < 1) {
                $vpadding = 1;
            }
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

    public function hasContent(): bool
    {
        return isset($this->content) && !empty($this->content);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function render(): array
    {
        $border = $this->getBorder();
        $padding = $this->getPadding();
        $vpadding = $this->getVerticalPadding() ?: ($border ? 0 : 1);
        $height = $this->getHeight();
        $width = $this->getWidth();
        $pad = str_repeat(' ', $padding);

        // Convert content string into array of strings
        $lines = $this->getFormattedContentLines();

        // colorize content
        if (static::$colorize) {
            foreach ($lines as $key => $contentLine) {
                if (empty($contentLine)) continue;
                $lines[$key] = Output::color($contentLine, $this->config()->color);
            }
        }

        // add horizontal padding to each row
        $lines = array_map(function (string $row) use ($pad) {
            return $pad . $row . $pad;
        }, $lines);

        $emptyRow = str_repeat(' ', $this->width - ($border * 2));
        // add vertical padding to rows
        if ($vpadding > 0) {
            // add padding rows: rows of spaces equal to longest row
            for ($p = 0; $p <= ($vpadding / 2); $p++) {
                array_unshift($lines, $emptyRow);
                array_push($lines, $emptyRow);
            }
        }

        // Add borders
        if ($border) {
            $borderStyle = $this->config()->get('border-style');
            $verticalBorder = Output::uchar('ver', $borderStyle);

            $topBorder = Output::uchar('down_right', $borderStyle);
            $topBorder .= str_repeat(Output::uchar('hor', $borderStyle), $this->width - ($border * 2));
            $topBorder .= Output::uchar('down_left', $borderStyle);

            array_unshift($lines, $topBorder);

            for ($i = 1; $i <= $this->height - 2; $i++) {
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

    public function scroll(string $direction, int $amount = 1)
    {
        $current = $this->offset;

        switch ($direction) {
            case 'up': // y--
                $amount = min($amount, $this->offset['y']);

                $this->offset['y'] -= $amount;
                break;
            case 'down': // y++
                $amount = min($amount, $this->offsetMax['y']);

                $this->offset['y'] += $amount;
                break;
            case 'left': // x--
                $amount = min($amount, $this->offset['x']);

                $this->offset['x'] -= $amount;
                break;
            case 'right': // x++
                $amount = min($amount, $this->offsetMax['x']);

                $this->offset['x'] += $amount;
                break;
        }

        if ($this->offset !== $current) {
            $this->getFormattedContentLines(true);
        }
    }

    public function setContent($content = '')
    {
        if (!is_string($content) && !($content instanceof Component)) {
            throw new \InvalidArgumentException(sprintf('Component content must be a string or a Component instance, "%s" given.', gettype($content)));
        }

        $this->content = $content;
        $this->getFormattedContentLines(true);
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
    public function setOffset(int $y = null, int $x = null): self
    {
        $this->offset['y'] = $y ?? $this->offset['y'] ?? 0;
        $this->offset['x'] = $x ?? $this->offset['x'] ?? 0;

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

        $config = new \stdClass;

        foreach ($viewConfig->getData() as $key => $value) {
            if ($key === 'styles') continue;
            $config->$key = $value;
        }

        foreach ($this->defaults as $key => $value) {
            if (!isset($config->$key)) {
                $config->$key = $value;
            }
        }

        if ($viewConfig->has($thisStylesKey)) {
            foreach (Arr::fromObject($viewConfig->get($thisStylesKey)) as $key => $value) {
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


        ///////////


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
        $height -= ($vpadding * 2);
        $height -= ($borders * 2);

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