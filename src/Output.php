<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Traits\RequiresBinary;
use PhpCli\Traits\SystemInterface;

class Output
{
    use RequiresBinary, SystemInterface;

    protected static $allow_unicode = true;

    protected static $variant;

    protected static $foreground_colors = [
        'black' => '0;30',
        'dark_gray' => '1;30',
        'red' => '0;31',
        'light_red' => '1;31',
        'green' => '0;32',
        'light_green' => '1;32',
        'brown' => '0;33',
        'yellow' => '1;33',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'light_gray' => '0;37',
        'white' => '1;37'
    ];

    protected static $background_colors = [
        'black' => '40',
        'dark_gray' => '1;40',
        'red' => '41',
        'light_red' => '1;41',
        'green' => '42',
        'light_green' => '1;42',
        'brown' => '43',
        'yellow' => '1;43',
        'blue' => '44',
        'light_blue' => '1;44',
        'purple' => '45',
        'light_purple' => '1;45',
        'cyan' => '46',
        'light_cyan' => '1;46',
        'light_gray' => '47',
        'white' => '1;47'
    ];

    protected static $control_chars = [
        'bold' => ["\033[1m",  "\033[0m"]
    ];

    protected static $unicode_borders = [
        'light' => [
            'hor' => '─',
            'ver' => '│',
            'down_right' => '┌',
            'down_left' => '┐',
            'up_right' => '└',
            'up_left' => '┘',
            'ver_right' => '├',
            'ver_left' => '┤',
            'down_hor' => '┬',
            'up_hor' => '┴',
            'cross' => '┼'
        ],
        'heavy' => [
            'hor' => '━',
            'ver' => '┃',
            'down_right' => '┏',
            'down_left' => '┓',
            'up_right' => '┗',
            'up_left' => '┛',
            'ver_right' => '┣',
            'ver_left' => '┫',
            'down_hor' => '┳',
            'up_hor' => '┻',
            'cross' => '╋'
        ],
        'double' => [
            'hor' => '═',
            'ver' => '║',
            'down_right' => '╔',
            'down_left' => '╗',
            'up_right' => '╚',
            'up_left' => '╝',
            'ver_right' => '╠',
            'ver_left' => '╣',
            'down_hor' => '╦',
            'up_hor' => '╩',
            'cross' => '╬'
        ]
    ];

    private Buffer $buffer;

    public function __construct()
    {
    }

    public static function bold($input)
    {
        return static::$control_chars['bold'][0] . $input . static::$control_chars['bold'][1];
    }

    public function buffer()
    {
        if (!isset($this->buffer)) {
            $this->buffer = new Buffer();
        }

        return $this->buffer;
    }

    /**
     * @param int $moveUp
     */
    public static function clearLine(int $moveUp = 0)
    {
        if ($moveUp) {
            Cursor::moveUp($moveUp);
        }
        return self::tput('el');
    }

    public static function color($input, $color, $background_color = false): string
    {
        $out = "";

        if (is_object($input)) {
            $input = json_decode(json_encode($input), true);
        }
        if (is_array($input)) {
            foreach ($input as $str) {
                if (!empty($str)) {
                    $out .= static::color($str, $color, $background_color) . "\n";
                }
            }
            $out = trim($out, "\n");
        } else {
            if (array_key_exists($color, static::$foreground_colors)) {
                $out .= "\033[" . static::$foreground_colors[$color] . "m";
            }

            if ($background_color) {
                if (array_key_exists($background_color, static::$background_colors)) {
                    $out .= "\033[" . static::$background_colors[$background_color] . "m";
                }
            }

            if (is_string($input)) {
                $out .= $input . "\033[0m";
            }
        }

        return $out;
    }

    protected static function control_chars()
    {
        $chars = static::$control_chars;
        foreach (static::$foreground_colors as $name => $code) {
            $chars['fg' . $name] = ["\033[" . $code . "m", "\033[0m"];
        }
        foreach (static::$background_colors as $name => $code) {
            $chars['bg' . $name] = ["\033[" . $code . "m", "\033[0m"];
        }

        return $chars;
    }

    /**
     * @param string $string
     * @param int $indent
     */
    public function error(string $string = ''): void
    {
        fwrite(STDERR, rtrim($string) . "\n");
    }

    /**
     * $output->line('string');
     * "string\n"
     */
    public function line(string $line = '', int $indent = 0): void
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
            $this->print(sprintf(rtrim($format) . "\n", ...$vars));
        }
    }

    /**
     * Output an array of lines.
     * 
     * @param array $lines
     * @param int $indent
     * @return void
     */
    public function lines(array $lines, int $indent = 0): void
    {
        foreach ($lines as $line) {
            $this->line($line, $indent);
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
            fwrite(STDOUT, $string);
        }
    }

    /**
     * Output an array with row and column index lables.
     * 
     * @param array $array
     * @return void
     */
    public static function printIndexedArray(array $array): void
    {
        $printedCols = false;
        $row_width = strlen(count($array) . '') + 1;

        if (!is_array($array[0])) {
            throw new \InvalidArgumentException(sprintf('Inner values must be an array or an object that implements Countable, got "%s"', gettype($array[0])));
        }

        $col_width = strlen(count($array[0]) . '') + 1;

        print "\n[";
        foreach ($array as $y => $row) {
            if (!$printedCols) {
                print "    ";
                foreach ($row as $x => $char) {
                    print substr(str_pad($x . '', $col_width, ' ', STR_PAD_RIGHT), 0, $col_width);
                }
                $printedCols = true;
            }

            print "\n  " . str_pad($y . '', $row_width, ' ', STR_PAD_RIGHT);
            foreach ($row as $x => $char) {
                print $char . "  ";
            }
            print ',';
        }
        print "\n]";

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
            $this->print($indentStr . rtrim($string, "\n\r") . "\n");
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
            // print $string;
            fwrite(STDOUT, $string);
        }
    }

    /**
     * Width of current console
     */
    public static function cols()
    {
        return static::rtput('cols');
    }

    /**
     * Height of current console
     */
    public static function rows()
    {
        return static::rtput('lines');
    }

    public static function allow_unicode()
    {
        return static::$allow_unicode ?? null;
    }

    public static function line_joint($flags, $variant = null)
    {
        $out = '+';
        if (is_null($variant)) {
            $variant = static::variant();
        }
        if (static::allow_unicode()) {
            if (!is_array($flags)) {
                $flags = explode(',', $flags);
            }
            foreach ($flags as $flag) {
                $flag = str_replace('-', '_', strtolower($flag));
                switch ($flag) {
                    case 'top:left':
                        $out = static::uchar('down_right', $variant);
                        break;
                    case 'top:right':
                        $out = static::uchar('down_left', $variant);
                        break;
                    case 'mid:left':
                    case 'middle:left':
                        $out = static::uchar('ver_right', $variant);
                        break;
                    case 'mid:right':
                    case 'middle:right':
                        $out = static::uchar('ver_left', $variant);
                        break;
                    case 'bot:left':
                    case 'bottom:left':
                        $out = static::uchar('up_right', $variant);
                        break;
                    case 'bot:right':
                    case 'bottom:right':
                        $out = static::uchar('up_left', $variant);
                        break;
                }
            }
        }

        return $out;
    }

    public static function combine_lines($lineA, $lineB, $variant = null)
    {
        $out = '+';
        if (is_null($variant)) {
            $variant = static::variant();
        }
        if (static::allow_unicode()) {
            switch ($lineA) {
                case Output::uchar('hor', $variant): // ─
                    switch ($lineB) {
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('ver_left', $variant): // ┤
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('down_left', $variant): // ┐
                            return Output::uchar('down_hor', $variant); // ┬
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('up_left', $variant): // ┘
                            return Output::uchar('up_hor', $variant); // ┴
                        case Output::uchar('hor', $variant): // ─
                            return $lineA; // ─
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('up_hor', $variant): // ┴
                            return $lineB;
                    }
                    break;
                case Output::uchar('ver', $variant): // │
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('ver', $variant): // │
                            return $lineA; // │
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('ver_right', $variant): // ├
                            return Output::uchar('ver_right', $variant); // ├
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('ver_left', $variant): // ┤
                            return Output::uchar('ver_left', $variant); // ┤
                    }
                    break;
                case Output::uchar('down_right', $variant): // ┌
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('down_left', $variant): // ┐
                            return Output::uchar('down_hor', $variant); // ┬
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('up_right', $variant): // └
                            return Output::uchar('ver_right', $variant); // ├
                        case Output::uchar('down_right', $variant): // ┌
                            return $lineA; // ┌
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('down_hor', $variant): // ┬
                            return $lineB;
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                    }
                    break;
                case Output::uchar('down_left', $variant): // ┐
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('down_right', $variant): // ┌
                            return Output::uchar('down_hor', $variant); // ┬
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('up_left', $variant): // ┘
                            return Output::uchar('ver_left', $variant); // ┤
                        case Output::uchar('down_left', $variant): // ┐
                            return $lineA; // ┐
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('down_hor', $variant): // ┬
                            return $lineB;
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                    }
                    break;
                case Output::uchar('up_right', $variant): // └
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('up_left', $variant): // ┘
                            return Output::uchar('up_hor', $variant); // ┴
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('down_right', $variant): // ┌
                            return Output::uchar('ver_right', $variant); // ├
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('up_right', $variant): // └
                            return $lineA; // └
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('up_hor', $variant): // ┴
                            return $lineB;
                    }
                    break;
                case Output::uchar('up_left', $variant): // ┘
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                            return Output::uchar('up_hor', $variant); // ┴
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('down_left', $variant): // ┐
                            return Output::uchar('ver_left', $variant); // ┤
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('up_right', $variant): // └
                            return $lineA; // └
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('ver_left', $variant): // ┤
                            return $lineB;
                    }
                    break;
                case Output::uchar('ver_right', $variant): // ├
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('ver_right', $variant): // ├
                            return $lineA; // ├
                    }
                    break;
                case Output::uchar('ver_left', $variant): // ┤
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                            break;
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('ver_left', $variant): // ┤
                            return $lineA; // ┤
                            break;
                    }
                    break;
                case Output::uchar('down_hor', $variant): // ┬
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('down_hor', $variant): // ┬
                            return $lineA; // ┬
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('up_hor', $variant): // ┴
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                    }
                    break;
                case Output::uchar('up_hor', $variant): // ┴
                    switch ($lineB) {
                        case Output::uchar('hor', $variant): // ─
                        case Output::uchar('up_right', $variant): // └
                        case Output::uchar('up_left', $variant): // ┘
                        case Output::uchar('up_hor', $variant): // ┴
                            return $lineA; // ┴
                        case Output::uchar('ver', $variant): // │
                        case Output::uchar('down_right', $variant): // ┌
                        case Output::uchar('down_left', $variant): // ┐
                        case Output::uchar('ver_right', $variant): // ├
                        case Output::uchar('ver_left', $variant): // ┤
                        case Output::uchar('down_hor', $variant): // ┬
                        case Output::uchar('cross', $variant): // ┼
                            return Output::uchar('cross', $variant); // ┼
                    }
                    break;
                case Output::uchar('cross', $variant): // ┼
                    return $lineA; // ┼
            }
        }

        return $out;
    }

    public static function non_unicode_variant($input, $variant = null)
    {
        $out = $input;

        if (is_null($variant)) {
            $variant = static::variant();
        }
        switch ($input) {
            case 'down_right':
            case 'down_left':
            case 'up_right':
            case 'up_left':
            case 'ver_right':
            case 'ver_left':
            case 'down_hor':
            case 'up_hor':
            case 'cross':
            case '':
            case (static::line_joint('top:left',  $variant)):
            case (static::line_joint('top:right', $variant)):
            case (static::line_joint('mid:left',  $variant)):
            case (static::line_joint('mid:right', $variant)):
            case (static::line_joint('bot:left',  $variant)):
            case (static::line_joint('bot:right', $variant)):
                $out = '+';
                break;
            case 'ver':
            case (static::uchar('ver', $variant, true)):
                $out = '|';
                break;
            case 'hor':
            case (static::uchar('hor', $variant, true)):
                $out = '-';
                break;
        }

        return $out;
    }

    public static function string_length($string)
    {
        $control_chars = static::control_chars();

        foreach ($control_chars as $name => $chars) {
            if (false !== mb_strpos($string, $chars[0])) {
                $string = str_replace($chars[0], '', $string);
            }
        }
        if (false !== mb_strpos($string, "\033[0m")) {
            $string = str_replace("\033[0m", '', $string);
        }

        if (function_exists('mb_strlen') && function_exists('mb_detect_encoding')) {
            $length = mb_strlen($string, (mb_detect_encoding($string) ?: 'utf8'));
        } else {
            $string = iconv('ASCII', 'ASCII', $string);
            $length = strlen($string);
        }

        return $length;
    }

    /**
     * Execute a tput command.
     * Capture result to an array and return it.
     */
    public static function rtput(string $command): array
    {
        self::requireBinary('tput');
        return self::exec('tput', $command);
    }

    /**
     * Execute a tput command.
     * Returns the last line of the command output on success, and false on failure.
     */
    public static function tput($command): string
    {
        self::requireBinary('tput');
        return self::system('tput', $command);
    }

    public static function uchar($char, $variant = null, $override_allow_unicode = false)
    {
        $out = $char;

        if (is_null($variant)) {
            $variant = static::variant();
        }

        if (substr($char, 0, 2) == '\u') {
            $out = json_decode('"' . $char . '"');
        } else {
            $aliases = [
                'top_l' => 'down_right',
                'top_r' => 'down_left',
                'mid_l' => 'ver_right',
                'mid_r' => 'ver_left',
                'bot_l' => 'up_right',
                'bot_r' => 'up_left',
                'top_ver' => 'down_hor',
                'bot_ver' => 'up_hor'
            ];
            if (array_key_exists($char, $aliases)) $char = $aliases[$char];
            if (array_key_exists($variant, static::$unicode_borders)) {
                if (array_key_exists($char, static::$unicode_borders[$variant])) {
                    $out = static::$unicode_borders[$variant][$char];
                }
            } else {
                $out = static::non_unicode_variant($out, $variant);
            }
        }

        if (!static::allow_unicode() && false === $override_allow_unicode) {
            $out = static::non_unicode_variant($out, $variant);
        }

        return $out;
    }

    /**
     * Get the border variant.
     * 
     * @return string
     */
    public static function variant(): string
    {
        return static::$variant ?? 'light';
    }
}