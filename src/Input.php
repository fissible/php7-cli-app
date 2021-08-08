<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Validation\Validator;
use Seld\CliPrompt\CliPrompt;

class Input
{
    private $buffer;

    public static function prepare_prompt(string $prompt, $default = null, string $placeholder = null): string
    {
        if (isset($placeholder)) {
            $placeholder = Output::color($placeholder, 'dark_gray');
        }

        if (isset($default) || isset($placeholder)) {
            $placeholder = $placeholder ? $placeholder . ':' : '';
            $default ??= '';
            
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
                        $defaultString = sprintf('%s$%01.2f', $placeholder, $default);
                    } else {
                        $defaultString = sprintf('%s%01.2f', $placeholder, $default);
                    }
                    $prompt = sprintf('%s [%s]', rtrim($prompt), $defaultString);
                } else {
                    $prompt = rtrim($prompt) . ' [' . $placeholder . $default . ']';
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

    public static function prompt($prompt, $default = null, Validator $validator = null, bool $saveRestore = false)
    {
        if ($saveRestore) {
            Cursor::save();
        }

        $answer = static::readline($prompt);

        if (empty($answer) && !is_null($default)) {
            $answer = $default;
        }

        if (!is_null($validator)) {
            while ($validator->fails(['input' => $answer])) {
                // if validator does not have required return null
                if (!$validator->hasRule('required')) {
                    return null;
                }
                if ($saveRestore) {
                    Cursor::restore();
                }
                $answer = static::readline($prompt);
            }
        }

        return $answer;
    }

    public static function promptSecret(string $prompt): string
    {
        fwrite(STDOUT, $prompt);
        return CliPrompt::hiddenPrompt();
    }

    /**
     * Return a Validator with keyed rules.
     * eg. Input::validator(['required', 'date:Y-m-d'], ['required' => 'Date is required.'])
     */
    public static function validator(iterable $rules = [], iterable $messages = [])
    {
        if (!empty($messages)) {
            $messages = array_flip(array_map(function ($ruleName) {
                if (false === strpos($ruleName, 'input')) {
                    $ruleName = 'input.'.$ruleName;
                }
                return $ruleName;
            }, array_flip((array) $messages)));
        }

        return new Validator(['input' => $rules], $messages);
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