<?php declare(strict_types=1);

namespace PhpCli;

class Table
{
    private Buffer $buffer;

    private array $headers;

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
        $this->buffer->print($this->options['chars']['top-left']);
        foreach ($headers as $x => $header) {
            $width = $cellWidths[$x];
            $this->buffer->print(str_repeat($this->options['chars']['top'], $width));
            if ($x < ($cols - 1)) {
                $this->buffer->print($this->options['chars']['top-mid']);
            }
        }
        $this->buffer->printl($this->options['chars']['top-right']);

        if ($printHeaders) {
            // print headers: widths + count + 1
            $this->buffer->print($this->options['chars']['left']);
            foreach ($headers as $x => $header) {
                $width = $cellWidths[$x];
                $this->buffer->print(str_pad(' ' . $header, $width));
                if ($x < ($cols - 1)) {
                    $this->buffer->print($this->options['chars']['middle']);
                }
            }
            $this->buffer->printl($this->options['chars']['right']);

            // print header/body divider
            $this->buffer->print($this->options['chars']['left-mid']);
            foreach ($headers as $x => $header) {
                $width = $cellWidths[$x];
                $this->buffer->print(str_repeat($this->options['chars']['mid'], $width));
                if ($x < ($cols - 1)) {
                    $this->buffer->print($this->options['chars']['mid-mid']);
                }
            }
            $this->buffer->printl($this->options['chars']['right-mid']);
        }

        if ($rowCount) {
            foreach ($this->rows as $y => $row) {
                $this->buffer->print($this->options['chars']['left']);
                foreach ($headers as $x => $_header) {
                    $width = $cellWidths[$x];
                    $this->buffer->print(str_pad(' ' . $row[$x], $width));
                    
                    if ($x < ($cols - 1)) {
                        $this->buffer->print($this->options['chars']['middle']);
                    }
                }
                $this->buffer->printl($this->options['chars']['right']);
            }

        } else {
            $this->buffer->print($this->options['chars']['left']);
            $this->buffer->print(str_pad($this->options['no-data-string'], $innerLength, ' ', STR_PAD_BOTH));
            $this->buffer->printl($this->options['chars']['right']);
        }

        // Print bottom border
        $this->buffer->print($this->options['chars']['bottom-left']);
        foreach ($headers as $x => $header) {
            $width = $cellWidths[$x];
            $this->buffer->print(str_repeat($this->options['chars']['bottom'], $width));
            if ($x < ($cols - 1)) {
                $this->buffer->print($this->options['chars']['bottom-mid']);
            }
        }
        $this->buffer->printl($this->options['chars']['bottom-right']);

        return $this->buffer->flush();
    }

    public function width()
    {
        $cellWidths = $this->cellWidths();
        return array_sum($cellWidths) + count($cellWidths) - 1;
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
        $this->options = array_merge_recursive($this->options, $options);
        return $this;
    }
}