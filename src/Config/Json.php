<?php declare(strict_types=1);

namespace PhpCli\Config;

use PhpCli\Arr;
use PhpCli\Config\JsonPointer as Pointer;
use PhpCli\Exceptions\ConfigNotFoundException;
use PhpCli\Exceptions\JsonPointerResolutionException;
use PhpCli\Filesystem\File;
use PhpCli\Interfaces\Config;

class Json extends File implements Config
{
    private \stdClass $data;

    private static array $referencedFiles = [];

    public function __construct(string $path)
    {
        parent::__construct($path);

        $this->data = new \stdClass;

        if ($this->exists()) {
            $this->loadData();
        }
    }

    public function get(string $name)
    {
        if (empty($name)) {
            return $this->getData();
        }
        
        if (false !== strpos($name, '.')) {
            // $keys = explode('.', $name);
            // $data = $this->data;

            // foreach ($keys as $key) {
            //     if (isset($data->$key)) {
            //         $data = $data->$key;
            //     } else {
            //         $data = null;
            //     }
            // }

            // return $data;

            return array_reduce(explode('.', $name), function ($previous, $current) {
                return is_numeric($current) ? ($previous[$current] ?? null) : ($previous->$current ?? null); 
            }, $this->data);

        } elseif (isset($this->data->$name)) {
            return $this->data->$name;
        }

        return null;
    }

    public function getData(): \stdClass
    {
        return $this->data;
    }

    public function has(string $name): bool
    {
        if (empty($name)) {
            return !empty((array) $this->data);
        }

        if (false !== strpos($name, '.')) {
            $keys = explode('.', $name);
            $data = $this->data;

            foreach ($keys as $key) {
                if (isset($data->$key)) {
                    $data = $data->$key;
                } else {
                    $data = null;
                }
            }
 
            return !is_null($data);
        }

        return isset($this->data->$name);
    }

    // public function persist(string $path = null)
    // {
    //     throw new \Exception('DEPRECATE? REFACTOR?');
    //     if ($path) {
    //         if ($this->path !== $path) {
    //             throw new \InvalidArgumentException(sprintf('Config file already set to path "%s"', $this->path));
    //         }
    //         $this->setFile($path);
    //         if ($this->exists()) {
    //             $this->loadData();
    //         }
    //     }

    //     $contents = json_encode($this->data, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR, 256);
    //     $bytes = $this->write($contents);

    //     if ($this->exists()) {
    //         $this->loadData();
    //     }

    //     return $bytes;
    // }

    public function set(string $name, $value): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Cannot set an empty key.');
        }

        if (false !== strpos($name, '.')) {
            // Temporarily cast \stdClass to array
            $array = Arr::fromObject($this->data);

            if (Arr::set($array, $name, $value, '.')) {
                $this->data = Arr::toObject($array);
            } else {
                throw new \Exception(sprintf('%s: error setting value.', $name));
            }
        } else {
            if (!isset($this->data)) {
                $this->data = new \stdClass;
            }

            if (is_array($value) && Arr::isAssociative($value)) {
                $value = Arr::toObject($value);
            }

            $this->data->$name = $value;
        }

        return $this;
    }

    public function setData(\stdClass $data): self
    {
        $this->data = $data;

        $this->data = $this->replacePointerReferences($data);

        return $this;
    }

    public function toArray(): array
    {
        return Arr::fromObject($this->data);
    }

    public function write($contents = null, bool $append = false)
    {
        if ($append) throw new \InvalidArgumentException('Cannot append to a JSON file.');

        $bytes = 0;

        if (is_null($contents)) {
            $contents = $this->data;
        }

        if (is_array($contents)) {
            $contents = Arr::toObject($contents);
        }

        if ($contents instanceof \stdClass) {
            $contents = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR, 256);
        }

        $bytes = parent::write($contents, false);

        if (false !== $bytes && $this->exists()) {
            $this->loadData();
        }

        return $bytes;
    }

    public function __get($name) 
    {
        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return $this->has($name);
    }

    public function __set(string $name, $value) 
    {
        return $this->set($name, $value);
    }

    /**
     * Recursively find pointers.
     * 
     * @param \stdClass|array $data
     * @param array $prefix
     * @return array
     */
    private static function findPointers($data): array
    {
        $pointers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($data),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        while ($iterator->valid() && $iterator->getDepth() < 25) {
            $d = $iterator->getDepth();

            if ($iterator->hasChildren() && $iterator->getChildren()->offsetExists('$ref')) {
                if (JsonPointer::check($iterator->current())) {
                    $keys = [];
                    for ($i = 0; $i < $d; $i++) {
                        $keys[] = JsonPointer::escapePropertyName($iterator->getSubIterator($i)->key() . '');
                    }
                    $keys[] = JsonPointer::escapePropertyName($iterator->key() . '');
                    $pointers[] = $keys;
                }
            }

            $iterator->next();
        }

        return $pointers;
    }


    /**
     * Get the value a Pointer references
     * 
     * @params Pointer $Pointer
     * @return mixed
     */
    private function getPointerValue(Pointer $Pointer, array $data = [])
    {
        $value = null;
        if (empty($data)) {
            $data = $this->data ?? [];
        }
                
        if ($Pointer->isFile()) {
            // Get the current value from the referenced file
            $Config = $this->resolveRefConfigFile($Pointer);
            $value = $Config->get($Pointer->getReference());
        } else {
            $value = Arr::get($data, ltrim($Pointer->reference, '#'), '/', function ($key) {
                return Pointer::unescapePropertyName($key);
            });
        }

        if (is_null($value)) {
            throw new JsonPointerResolutionException($Pointer->reference);
        }

        return $value;
    }


    private function loadData(): self
    {
        if (!$this->exists()) {
            throw new ConfigNotFoundException($this->getPath());
        }

        return $this->setData(json_decode($this->read(), false, 256, JSON_THROW_ON_ERROR));
    }

    /**
     * Recursively update references to the resolved data.
     * 
     * @param array $data
     */
    private function replacePointerReferences(\stdClass $data, int $recurseLimit = 16): \stdClass
    {
        $sanitizeKey = function ($key) {
            return Pointer::unescapePropertyName($key);
        };
        
        $i = 0;
        while (!empty($pointers = static::findPointers($data)) && $i <= $recurseLimit) {
            $i++;

            // Temporarily cast \stdClass to array
            $data = Arr::fromObject($data);
            
            // get the pointer, replace key with resolved data
            foreach ($pointers as $keys) {
                if (empty($keys)) continue;
                $reference = '/'.implode('/', $keys);

                // Get the reference
                if (!$pointer = Arr::get($data, $reference, '/', $sanitizeKey)) {
                    throw new JsonPointerResolutionException($reference);
                }

                if (!Pointer::check($pointer)) continue;

                // Get the Pointer and resolve the value
                $Pointer = new Pointer(Arr::toObject($pointer));
                $value = $this->getPointerValue($Pointer, $data);

                // Replace the reference with the resolved value
                if (!Arr::set($data, $reference, $value, '/', false, $sanitizeKey)) {
                    throw new JsonPointerResolutionException($reference);
                }
            }

            // Revert $data array to \stdClass
            $data = Arr::toObject($data);
        }

        if ($i > $recurseLimit) {
            throw new \Exception(sprintf('%s hit recursion limit %d', __METHOD__, $recurseLimit));
        }

        return $data;
    }

    /**
     * If a JSON Pointer points to a file, resolve the path and return a Json Config instance.
     * 
     * @param Pointer $Pointer
     * @return Json
     */
    private function resolveRefConfigFile(Pointer $Pointer): Json
    {
        if (!$Pointer->isFile()) {
            throw new \InvalidArgumentException('Cannot resovle JSON Pointer to a file.');
        }

        $ref = $Pointer->reference;

        if (isset(static::$referencedFiles[$ref])) {
            return static::$referencedFiles[$ref];
        }

        $filepath = $Pointer->getFilepath();

        // Resolve relative file
        if (substr($filepath, 0, 1) !== DIRECTORY_SEPARATOR) {
            $filepath = $this->path($filepath);
        }

        $ConfigFile = new Json($filepath);

        if (!$ConfigFile->exists()) {
            throw new ConfigNotFoundException($filepath);
        }

        static::$referencedFiles[$ref] = $ConfigFile;

        return static::$referencedFiles[$ref];
    }
}