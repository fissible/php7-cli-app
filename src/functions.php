<?php
/**
 * Helper functions
 */

use PhpCli\Output;

$errors = [];

// if (! function_exists('banner')) {
//     function banner($input, $title = null, $color = 'white', $indent = '', $print_horizontal_borders = true) {
//         $bordr = Output::color(Output::uchar('ver', 'heavy'), $color);
//         $width = floor(Output::cols() / 1.5);

//         if ($print_horizontal_borders) {
//             Output::line(Output::color(Output::horizontal_line('top', $width, 'heavy'), $color), '', $width);

//         }
//         if ($title) {
//             Output::line(Output::color($title, $color), $bordr, $width);
//         }

//         if (is_array($input)) {
//             $input = print_r($input, true);
//             $input = explode("\n", $input);
//             foreach ($input as $str) {
//                 if (! empty($str)) {
//                     banner($str, null, $indent, $color, false);
//                 }
//             }
//         } elseif (! empty($input)) {
//             Output::line($indent.$input, $bordr, $width);
//         }
//         if ($print_horizontal_borders) {
//             Output::line(Output::color(Output::horizontal_line('bot', $width, 'heavy'), $color), '', $width);
//         }
//     }
// }

function array_first(array $array, callable $filter)
{
    foreach (array_filter($array, $filter) as $result) {
        return $result;
    }

    return null;
}

function caller($key = null, $index = 0)
{
    $trace = debug_backtrace(false);
    $caller = $trace[$index];
    /*
    [   'file' => "... /src/Models/Task.php"
        'line' => 172
        'function' => "caller"
        'args' => []                            ]
     */
    if (! is_null($key)) {
        if (array_key_exists($key, $caller)) {
            return $caller[$key];
        } else {
            $output = $key;
            foreach ($caller as $caller_key => $val) {
                if (is_array($val)) continue;
                if (false !== strpos($key, $caller_key)) {
                    $output = str_replace($caller_key, $val, $output);
                }
            }

            return $output;
        }
    }

    return $caller;
}

if (! function_exists('coalesce')) {
    function coalesce(...$inputs)
    {
        foreach ($inputs as $input) {
            if (! is_null($input) && ! empty($input)) return $input;
        }
    }
}

// function debug($input = '', $color = 'yellow', $internally_invoked = false)
// {
//     if (DEVELOPMENT_MODE == true) {
//         $bordr = Output::color(Output::uchar('ver', 'heavy'), $color);
//         $width = floor(Output::cols() / 1.5);

//         if (! $internally_invoked) {
//             Output::line(Output::color(Output::horizontal_line('top', $width, 'heavy'), $color), '', $width);
//             Output::line(Output::color(' DEBUG  '.caller('file:line', 1), $color), $bordr, $width);
//         }
//         if (is_null($input)) {
//             $input = gettype($input);
//         } elseif (true === $input) {
//             $input = 'true';
//         } elseif (false === $input) {
//             $input = 'false';
//         } elseif (is_object($input)) {
//             $input = @var_export($input, true);
//             $input = explode("\n", $input);
//         }

//         if (is_array($input)) {
//             $input = print_r($input, true);
//             $input = explode("\n", $input);
//             foreach ($input as $str) {
//                 if (! empty($str)) {
//                     debug($str, $color, true);
//                 }
//             }
//         } elseif (! empty($input)) {
//             Output::line('       '.$input, $bordr, $width);
//         }
//         if (! $internally_invoked) {
//             Output::line(Output::color(Output::horizontal_line('bot', $width, 'heavy'), $color), '', $width);
//         }
//     }
// }

function deprecated($class = null, $function = null, $line = null)
{
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    if (is_null($class)) {
        $class = $caller['file'];
    }
    if (is_null($function)) {
        $function = $caller['function'];
    }
    if (is_null($line)) {
        $line = $caller['line'];
    }
    if (php_sapi_name() === 'cli') {
        printl(sprintf('DEPRECATED - %s %s[%d]', $function, $class, $line));
    }
}

if (! function_exists('vdump')) {
    function vdump($value)
    {
        $file = __FILE__;
        $caller = caller(null, 1);

        ob_start();
        if (is_scalar($value)) {
            print $value;
        } else {
            var_dump($value);
        }
        $out = ob_get_clean();

        // Replace the helpful path/ line no. that prints from var_dump() with caller file and line.
        $matches = [];
        if (preg_match('#'. $file . ':(\d+)#', $out, $matches)) {
            $out = str_replace($matches[0], $caller['file'] . ':' . $caller['line'], $out);
        }

        fwrite(STDOUT, $out);
    }
}

function env($key, $default = null)
{
    $value = getenv($key);

    if (preg_match_all('/{+(.*?)}/', $value, $matches)) {
        foreach ($matches as $match) {
            if (defined($match[0])) {
                $value = \PhpCli\Str::_replace($value, '/{'.$match[0].'}/', constant($match[0]), true, 1);
            }
        }
    }

    if (is_null($value) || strtolower($value) === 'null' || false === $value) {
        $value = $default;
    } elseif (in_array(strtolower($value), ['false', 'true', 'yes', 'no', 'on', 'off'])) {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    } elseif (is_numeric($value)) {
        if ((int) $value == $value) {
            $value = intval($value);
        } else {
            $value = floatval($value);
        }
    }

    return $value;
}

// /**
//  * Add an error to the errors array
//  * @param null $error_msg
//  * @param null $command
//  */
// function error($error_msg = null, $command = null)
// {
//     global $errors;
//     if (!is_null($error_msg)) {
//         printl($error_msg);
//     }
//     if (! is_null($command)) {
//         usage($command);
//     }
// }

// /**
//  * Output errors and return with status code 1
//  * @param null $error_msg
//  * @param int $exit_code
//  */
// function error_exit($error_msg = null, $exit_code = 1)
// {
//     global $errors;
//     error($error_msg);
//     show_errors();
//     exit($exit_code);
// }

// /**
//  * Output errors
//  */
// function show_errors()
// {
//     global $errors;
//     if (count($errors)) {
//         banner(implode("\n", Output::color($errors, 'red')), '', 'red');
//         // printl(implode("\n", Output::color($errors, 'red')));
//     }
// }

/**
 * Handle the Application->run() result
 * @param  mixed $result The return value of the Application->run() method
 */
function handle_result($result = null)
{
    $nested_array = false;
    $coerce_string = App()->Command()->Options()->exist('s');

    if (is_array($result)) {
        foreach ($result as $key => $value) {
            if ($nested_array = is_array($value)) {
                break;
            }
        }
        if (! $nested_array && $coerce_string) {
            $result = implode(' ', $result);
        }
    }

    if (is_array($result)) {
        if (php_sapi_name() === 'cli' && ! $coerce_string) {
            if (! empty($result)) {
                print json_encode($result, JSON_PRETTY_PRINT);
            }
        } else {
            print json_encode($result);
        }
    } elseif ($result) {
        dump($result);
    }
}

function printl($value = '')
{
    dump($value);
    print "\n";
}

/**
 * Local implementation of readline
 * @param  string $prompt The prompt message
 * @return string The user input
 */
if (! function_exists('readline')) {
    function readline($prompt = null)
    {
        if ($prompt) echo $prompt;
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024), "\r\n");

        return $line;
    }
}

if (! function_exists('readline_secret')) {
    function readline_secret($prompt = null)
    {
        if ($prompt) echo $prompt;
        exec('stty -echo');
        $line = trim(fgets(STDIN));
        exec('stty echo');
        printl();

        return $line;
    }
}

if (! function_exists('tap')) {
    function tap($value, $callback)
    {
        $callback($value);

        return $value;
    }
}

if (! function_exists('unwrap')) {
    function unwrap($input, $check_if_one = true)
    {
        if ((is_array($input) || $input instanceof \stdClass) && ($check_if_one !== true || count($input) === 1)) {
            return current($input);
        }

        return $input;
    }
}

if (! function_exists('config')) {
    function config($key)
    {
        $config = null;

        if (false !== strpos($key, '.')) {
            $parts = explode('.', $key);
        } else {
            $parts = (array) $key;
        }

        if (count($parts) > 0) {
            switch ($parts[0]) { // config('database')
                case 'database':
                    $config = include(DATABASE_PATH.'/config/'.(env('APP_ENV') ?: 'local').'.php');

                    if (isset($parts[1])) { // config('database.sqlite')
                        $driver = $parts[1];

                        if (isset($config[$driver])) {
                            $config = $config[$driver];

                            switch (strtolower($driver)) {
                                case 'sqlite':
                                    $config['driver'] = 'sqlite';
                                    $config['database'] = $config['path'];

                                    if (! isset($config['prefix'])) {
                                        $config['prefix'] = '';
                                    }
                                    break;
                                case 'mysql':
                                    $config['driver'] = 'mysql';
                                    $config['database'] = $config['path'];

                                    if (! isset($config['charset'])) {
                                        $config['charset'] = 'utf8mb4';
                                    }
                                    if (! isset($config['collation'])) {
                                        $config['collation'] = 'utf8mb4_unicode_ci';
                                    }
                                    if (! isset($config['prefix'])) {
                                        $config['prefix'] = '';
                                    }
                                    break;
                            }

                            if (isset($parts[2])) { // config('database.sqlite.collation')
                                if (isset($config[$parts[2]])) {
                                    $config = $config[$parts[2]];
                                }
                            }
                        }
                    }
                    break;

            }
        }

        return $config;
    }
}

// /**
//  * @param $driver
//  * @param int $attempts
//  * @return mixed
//  * @throws \Predis\Connection\ConnectionException
//  */
// function database($driver, $attempts = 0)
// {
//     $Handle = null;
//     $config = config('database.'.$driver);

//     // Illuminate/Capsule BEGIN
//     eloquent();
//     // Illuminate/Capsule END

//     if (false === strpos($driver, 'Worklog\\Database\\Drivers')) {
//         $driver = 'Worklog\\Database\\Drivers\\'.$driver;
//     }
//     if (false === stripos($driver, 'DatabaseDriver')) {
//         $driver .= 'DatabaseDriver';
//     }
//     if (false === stripos($driver, 'Driver')) {
//         $driver .= 'Driver';
//     }
//     try {
//         $Handle = new $driver($config);
//     } catch (Predis\Connection\ConnectionException $e) {
//         if ($attempts < 10) {
//             passthru('bash '.APPLICATION_PATH.'/start-redis-server > /dev/null');
//             sleep(1);

//             return database($driver, ++$attempts);
//         } else {
//             throw $e;
//         }
//     }

//     return $Handle;
// }

// function eloquent() {
//     return Worklog\Database\Connection::getInstance(config('database.'.getenv('DATABASE_DRIVER')));
// }

function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT)
{
    $str_len = mb_strlen($str);
    $pad_str_len = mb_strlen($pad_str);
    if (!$str_len && ($dir == STR_PAD_RIGHT || $dir == STR_PAD_LEFT)) {
        $str_len = 1; // @debug
    }
    if (!$pad_len || !$pad_str_len || $pad_len <= $str_len) {
        return $str;
    }

    $result = null;
    if ($dir == STR_PAD_BOTH) {
        $length = ($pad_len - $str_len) / 2;
        $repeat = ceil($length / $pad_str_len);
        $result = mb_substr(str_repeat($pad_str, $repeat), 0, floor($length))
            . $str
            . mb_substr(str_repeat($pad_str, $repeat), 0, ceil($length));
    } else {
        $repeat = ceil($str_len - $pad_str_len + $pad_len);
        if ($dir == STR_PAD_RIGHT) {
            $result = $str . str_repeat($pad_str, $repeat);
            $result = mb_substr($result, 0, $pad_len);
        } elseif ($dir == STR_PAD_LEFT) {
            $result = str_repeat($pad_str, $repeat);
            $result = mb_substr($result, 0,
                    $pad_len - (($str_len - $pad_str_len) + $pad_str_len))
                . $str;
        }
    }

    return $result;
}
