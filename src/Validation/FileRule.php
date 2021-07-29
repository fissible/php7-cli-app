<?php declare(strict_types=1);

namespace PhpCli\Validation;

use PhpCli\Filesystem\File;

class FileRule extends RegexRule
{
    protected string $name = 'file';

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must be a path to a file.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return bool
     */
    public function passes(string $name, $input): bool
    {
        if (is_string($input)) {
            $File = new File($input);

            return $File->exists();
        }

        return false;
    }
}