<?php declare(strict_types=1);

namespace PhpCli;

use Seld\CliPrompt\CliPrompt;

class Input
{
    private $buffer;

    public static function prepare_prompt(string $prompt, $default = null): string
    {
        if (!is_null($default)) {
            // Separate provided suffix
            $suffix = '';
            while (in_array($char = substr($prompt, -1), [' ', ':', '?', '>', '$', '%'])) {
                $prompt = rtrim($prompt, $char);
                $suffix .= $char;
            }
            $suffix = \strrev($suffix);

            // "enter value"        -> "enter value [default]" -- append default value
            // "enter value [N/A]"  -> "enter value [N/A]"     -- leave as-is
            // If the prompt does not end with a bracketed value.
            if (preg_match('/.*\[([^)]*)\]$/', $prompt) !== 1) {
                if (is_float($default)) {
                    if (substr($suffix, -1) === '$') {
                        $prompt = sprintf('%s [$%01.2f]', rtrim($prompt), $default);
                    } else {
                        $prompt = sprintf('%s [%01.2f]', rtrim($prompt), $default);
                    }
                } else {
                    $prompt = rtrim($prompt) . ' [' . $default . ']';
                }
            }

            // "enter value [default]" -> "enter value [default]: "
            $prompt .= $suffix;
            if (in_array(substr($prompt, -1), [':', '?', '>'])) {
                $prompt .= ' ';
            }
        }

        return $prompt;
    }

    public static function prompt($prompt, $default = null, $required = false)
    {
        $answer = static::readline($prompt);

        if (empty($answer) && !is_null($default)) {
            $answer = $default;
        }

        while (empty($answer) && $required) {
            $answer = static::readline($prompt);
        }

        return $answer;
    }

    public static function promptSecret(string $prompt): string
    {
        fwrite(STDOUT, $prompt);
        return CliPrompt::hiddenPrompt();
    }

    public static function yesNo($prompt, $default = 'y'): bool
    {
        fwrite(STDOUT, $prompt);
        $input = strtolower(fread(STDIN, 1) ?: $default);

        return $input === 'y';
    }

    private static function readline(string $prompt): string
    {
        if (extension_loaded('readline')) {
            $answer = readline($prompt);
            $answer = preg_replace('{\r?\n$}D', '', (string) $answer) ?: '';
            rtrim($answer, " ");
        } else {
            fwrite(STDOUT, $prompt);
            $answer = CliPrompt::prompt();
        }
        return $answer;
    }
}