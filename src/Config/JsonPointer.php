<?php declare(strict_types=1);

namespace PhpCli\Config;

use PhpCli\Traits\MagicProxy;

class JsonPointer
{
    use MagicProxy;

    // private Json $Config;

    /**
     * The Config\Json compatible dot path, eg. "schema.models.User"
     */
    private string $path;

    /**
     * The Json Pointer reference string, eg. "/schema/models/User"
     */
    private string $reference;

    public function __construct(/*Json $Config, */\stdClass $pointer = null)
    {
        // $this->Config = $Config;

        if ($pointer) {
            $this->set($pointer);
        }
    }

    public function getFilepath(): string
    {
        if (!$this->isFile()) {
            throw new \InvalidArgumentException(sprintf('%s: cannot resovle JSON Pointer to a file.', $this->reference));
        }

        // "configs/api.json#/sites/OpenLibrary"
        if (false !== ($pos = strpos($this->reference, '.json#'))) {
            return substr($this->reference, 0, $pos + 5);
        }

        return $this->reference;
    }

    public function getReference(): string
    {
        if (false !== ($pos = strpos($this->reference, '.json#'))) {
            return substr($this->reference, $pos + 6);
        }

        return $this->reference;
    }

    /**
     * Check if the reference is for an absolute file path, eg.
     *  "/users/aj/configs/api.json"
     */
    public function isAbsoluteFile(): bool
    {
        if (strlen($this->reference) === 0) return false;
        return strpos($this->reference, '.json') !== false && $this->reference[0] === DIRECTORY_SEPARATOR;
    }

    /**
     * Check if the reference is for a file path, eg.
     *  "/users/aj/configs/api.json"
     *  "configs/api.json"
     */
    public function isFile(): bool
    {
        if (strlen($this->reference) === 0) return false;
        return strpos($this->reference, '.json') !== false;
    }

    /**
     * Check if the reference is for a file path plus a reference, eg.
     *  "configs/api.json#/sites/OpenLibrary"
     */
    public function isFileWithReference(): bool
    {
        if (strlen($this->reference) === 0) return false;
        return strpos($this->reference, '.json#') !== false;
    }

    /**
     * Check if the reference is regular (non-file), eg.
     *  "/schema/models/User"
     *  "#/definitions/Order
     */
    public function isReference(): bool
    {
        if (strlen($this->reference) === 0) return false;
        return $this->reference[0] === '/' && !$this->isFile();
    }

    /**
     * Check if the reference is for a relative file path, eg.
     *  "configs/api.json"
     */
    public function isRelativeFile(): bool
    {
        if (strlen($this->reference) === 0) return false;
        return strpos($this->reference, '.json') !== false && $this->reference[0] !== DIRECTORY_SEPARATOR;
    }

    public function toStdClass(): \stdClass
    {
        $key = '$ref';
        $pointer = new \stdClass;
        $pointer->$key = $this->reference;

        return $pointer;
    }

    /**
     * Set the pointer ref and dotpath.
     * 
     * @param array|\stdClass $pointer
     * @return self
     */
    public function set($pointer): self
    {
        static::validateRef($pointer);

        $this->reference = static::getReferenceFromPointer($pointer);
        $this->path = static::parseRef($this->reference);

        return $this;
    }

    public static function check($ref)
    {
        if ($ref instanceof \stdClass || is_array($ref)) {
            return array_key_first((array) $ref) === '$ref';
        }

        return false;
    }

    /**
     * "a/~b" -> "a~1~0b"
     */
    public static function escapePropertyName(string $name)
    {
        $name = str_replace('~', '~0', $name);
        $name = str_replace('/', '~1', $name);

        return $name;
    }

    /**
     * Parse a ref string "#/components/schemas/ArrayOfUsers"
     * into a dot string "components.schemas.ArrayOfUsers"
     * 
     * @param array|string $ref
     * @return string
     */
    public static function parseRef(string $ref): string
    {
        /*
{
    "foo": ["bar", "baz"],
    "": 0,
    "a/b": 1,
    "c%d": 2,
    "e^f": 3,
    "g|h": 4,
    "i\\j": 5,
    "k\"l": 6,
    " ": 7,
    "m~n": 8
}
        */
        /*
<empty>      // the whole document
/foo"       ["bar", "baz"]
/foo/0"     "bar"
/"          0
/a~1b       1
/c%d        2
/e^f        3
/g|h        4
/i\j        5
/k"l        6
/           7
/m~0n       8
        */

        // # should be used to denote a subpath on a file reference, eg. "definitions.json#/Pet"
        // but can prefix the ref in some schemas
        $ref = ltrim($ref, '#');

        if (empty($ref)) {
            return '.'; // the whole document
        }

        // If ref includes a file path, wrap in [brackets] and parse any remaining ref
        $pos = strpos($ref, '.json#');
        if ($pos !== false) {
            list($path, $ref) = explode('#', $ref);
            $ref = str_replace('/', '.', $ref);

            return '['.$path.'#'.$ref.']';
        }

        $parts = explode('/', $ref);
        $parts = array_map(function ($part) {
            if (is_numeric($part)) {
                return $part + 0;
            }
            return $part;
        }, $parts);

        $dotpath = implode('.', $parts);
        $dotpath = trim($dotpath, '.');

        return $dotpath;
    }

    /**
     * "a~1~0b" -> "a/~b"
     */
    public static function unescapePropertyName(string $name)
    {
        $name = str_replace('~1', '/', $name);
        $name = str_replace('~0', '~', $name);

        return $name;
    }

    /**
     * Validate a pointer.
     * 
     * @param array|\stdClass $pointer
     */
    public static function validateRef($ref)
    {
        if (!static::check($ref)) {
            throw new \InvalidArgumentException(sprintf('%s: not a valid JSON Reference.', substr(var_export($ref, true), 0, 75)));
        }
    }

    /**
     * Parse the incoming pointer (already validated).
     * 
     * @param array|\stdClass $pointer
     * @return string
     */
    private static function getReferenceFromPointer($pointer): string
    {
        if ($pointer instanceof \stdClass) {
            $key = '$ref';
            return $pointer->$key;
        }

        return $pointer['$ref'];
    }
}