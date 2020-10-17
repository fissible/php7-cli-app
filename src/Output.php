<?php declare(strict_types=1);

namespace PhpCli;

class Output
{
    private Buffer $buffer;

    public function __construct()
    {
    }

    public function buffer()
    {
        if (!isset($this->buffer)) {
            $this->buffer = new Buffer();
        }

        return $this->buffer;
    }

    /**
     * $output->line('string');
     * "string\n"
     */
    public function line(string $line = '', $indent = 0): void
    {
        $this->printl($line, $indent);
    }

    /**
     * $output->linef('value: %s', 'string');
     * "value: string\n"
     */
    public function linef(string $format, ...$vars): void
    {
        if (isset($this->buffer)) {
            $this->buffer->printlf($format, ...$vars);
        } else {
            printf(rtrim($format) . "\n", ...$vars);
        }
    }

    /**
     * $output->print('string');
     * "string"
     */
    public function print(string $string): void
    {
        if (isset($this->buffer)) {
            $this->buffer->print($string);
        } else {
            print $string;
        }
    }

    /**
     * $output->printl('string');
     * "string\n"
     */
    public function printl(string $string, $indent = 0): void
    {
        $indentStr = str_repeat('  ', $indent);
        if (isset($this->buffer)) {
            $this->buffer->printl($indentStr . $string);
        } else {
            print $indentStr . rtrim($string) . "\n";
        }
    }

    public function flush()
    {
        $output = $this->buffer->flush();
        unset($this->buffer);

        return $output;
    }

    public function send(): void
    {
        $output = $this->flush();

        foreach ($output as $string) {
            print $string;
        }
    }
}