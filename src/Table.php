<?php declare(strict_types=1);

namespace PhpCli;

class Table
{
    private Buffer $buffer;

    private array $headers;

    private $maskDuplicateRowValues = false;

    private array $rows;

    private array $options = [
        'chars' => [
            'top' => '─',
            'top-mid' => '┬',
            'top-left' => '┌',
            'top-right' => '┐',
            'bottom' => '─',
            'bottom-mid' => '┴',
            'bottom-left' => '└',
            'bottom-right' => '┘',
            'left' => '│',
            'left-mid' => '├',
            'mid' => '─',
            'mid-mid' => '┼',
            'right' => '│',
            'right-mid' => '┤',
            'middle' => '│'
        ],
        'no-data-string' => 'No data'
    ];

    public function __construct(Application $app, array $headers = [], array $rows = [], array $options = [])
    {
        $this->app = $app;
        $this->buffer = new Buffer();
        $this->headers = $headers;
        $this->rows = $rows;
        $this->setOptions($options);
    }

    public static function borderPreset(string $preset): array
    {
        switch ($preset) {
            case 'none':
                return [
                    'chars' => [
                        'top' => '',
                        'top-mid' => '',
                        'top-left' => '',
                        'top-right' => '',
                        'bottom' => '',
                        'bottom-mid' => '',
                        'bottom-left' => '',
                        'bottom-right' => '',
                        'left' => '',
                        'left-mid' => '',
                        'mid' => '',
                        'mid-mid' => '',
                        'right' => '',
                        'right-mid' => '',
                        'middle' => ''
                    ]
                ];
            break;
        }
    }

    /**
     * Print the table to the output
     */
    public function print(): void
    {
        foreach ($this->render() as $key => $line) {
            $this->app->output->print($line);
        }
    }

    public function render()
    {
        $printHeaders = false;
        $cellWidths = [];
        $rowCount = count($this->rows);

        $this->buffer->clean();

        if (empty($this->headers)) {
            $cols = 0;
            foreach ($this->rows as $row) {
                $c = count($row);
                if ($c > $cols) $cols = $c;
            }
            $headers = array_fill(0, $cols, ' ');
            $cellWidths = array_fill(0, $cols, 0);
        } else {
            $cols = count($this->headers);
            $cellWidths = array_fill(0, $cols, 0);
            $headers = $this->headers;
            $printHeaders = true;

            foreach ($this->headers as $x => $header) {
                $w = strlen($header) + 2;
                if ($w > $cellWidths[$x]) $cellWidths[$x] = $w;
            }
        }

        foreach ($this->rows as $y => $row) {
            foreach ($row as $x => $col) {
                if (isset($cellWidths[$x])) {
                    $w = strlen((string) $col) + 2;
                    if ($w > $cellWidths[$x]) $cellWidths[$x] = $w;
                }
            }
        }

        $innerLength = array_sum($cellWidths) + count($cellWidths) - 1;

        // print top border
        $this->printChar('top-left');

        $char_top = $this->getChar('top');
        $char_top_mid = $this->getChar('top-mid');
        if ($char_top || $char_top_mid) {
            foreach ($headers as $x => $header) {
                $width = $cellWidths[$x];
                if ($char_top) {
                    $this->buffer->print(str_repeat($char_top, $width));
                }
                if ($x < ($cols - 1) && $char_top_mid) {
                    $this->buffer->print($char_top_mid);
                }
            }
        }

        $this->printChar('top-right', true);

        if ($printHeaders) {
            // print headers: widths + count + 1

            $this->printChar('left');
            foreach ($headers as $x => $header) {
                $width = $cellWidths[$x];
                $this->buffer->print(str_pad(' ' . $header, $width));
                if ($x < ($cols - 1)) {
                    $this->printChar('middle');
                }
            }
            $this->printChar('right', true);

            // print header/body divider
            $this->printChar('left-mid');
            foreach ($headers as $x => $header) {
                $width = $cellWidths[$x];
                if ($char = $this->getChar('mid')) {
                    $this->buffer->print(str_repeat($char, $width));
                }
                if ($x < ($cols - 1)) {
                    $this->printChar('mid-mid');
                }
            }
            $this->printChar('right-mid', true);
        }

        if ($rowCount) {
            foreach ($this->rows as $y => $row) {
                $this->printChar('left');
                foreach ($headers as $x => $_header) {
                    $width = $cellWidths[$x];
                    $prev_cell_value = null;
                    if ($y > 0 && isset($this->rows[$y - 1]) && isset($this->rows[$y - 1][$x])) {
                        $prev_cell_value = $this->rows[$y - 1][$x];
                    }
                    $cell_value = isset($row[$x]) ? $row[$x] : '';

                    if (($this->maskDuplicateRowValues === true || is_array($this->maskDuplicateRowValues) && in_array($x, $this->maskDuplicateRowValues)) && !empty($prev_cell_value)) {
                        if (isset($row[$x]) && $row[$x] === $prev_cell_value && strlen($cell_value) > 7) {
                            $cell_value = trim(substr($cell_value, 0, 5)).'...';
                        }
                    }
                    $this->buffer->print(str_pad(' ' . $cell_value, $width));

                    if ($x < ($cols - 1)) {
                        $this->printChar('middle');
                    }
                }
                $this->printChar('right', true);
            }

        } else {
            $this->printChar('left');
            $this->buffer->print(str_pad($this->options['no-data-string'], $innerLength, ' ', STR_PAD_BOTH));
            $this->printChar('right', true);
        }

        // Print bottom border
        $this->printChar('bottom-left');
        foreach ($headers as $x => $header) {
            $width = $cellWidths[$x];
            if ($char = $this->getChar('bottom')) {
                $this->buffer->print(str_repeat($char, $width));
            }
            if ($x < ($cols - 1)) {
                $this->printChar('bottom-mid');
            }
        }
        $this->printChar('bottom-right', true);

        return $this->buffer->flush();
    }

    /**
     * Pass in true to mask all repeating values or
     * an array of inter indexes that should mask.
     *
     * @param bool|array
     * @return self
     */
    public function setMaskDuplicateRowValues($value): self
    {
        $this->maskDuplicateRowValues = $value;
        return $this;
    }

    public function width()
    {
        $cellWidths = $this->cellWidths();
        return array_sum($cellWidths) + count($cellWidths) - 1;
    }

    private function getChar($name): ?string
    {
        if (isset($this->options['chars'][$name]) && !empty($this->options['chars'][$name])) {
            return $this->options['chars'][$name];
        }
        return null;
    }

    private function printChar($name, bool $newline = false): void
    {
        if ($char = $this->getChar($name)) {
            if ($newline) {
                $this->buffer->printl($char);
            } else {
                $this->buffer->print($char);
            }
        } elseif ($newline) {
            $this->buffer->printl('');
        }
    }

    private function cellWidths(): array
    {
        $cellWidths = [];

        if (empty($this->headers)) {
            $cols = 0;
            foreach ($this->rows as $row) {
                $c = count($row);
                if ($c > $cols) $cols = $c;
            }
            $cellWidths = array_fill(0, $cols, 0);
        } else {
            $cols = count($this->headers);
            $cellWidths = array_fill(0, $cols, 0);

            foreach ($this->headers as $x => $header) {
                $w = strlen($header) + 2;
                if ($w > $cellWidths[$x]) $cellWidths[$x] = $w;
            }
        }

        foreach ($this->rows as $y => $row) {
            foreach ($row as $x => $col) {
                if (isset($cellWidths[$x])) {
                    $w = strlen((string) $col) + 2;
                    if ($w > $cellWidths[$x]) $cellWidths[$x] = $w;
                }
            }
        }

        return $cellWidths;
    }

    /**
     * @param array $options
     * @return $this
     */
    private function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $_key => $_value) {
                    $this->options[$key][$_key] = $_value;
                }
            } else {
                $this->options[$key] = $value;
            }
        }
        return $this;
    }
}