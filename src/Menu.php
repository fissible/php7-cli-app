<?php declare(strict_types=1);

namespace PhpCli;

class Menu
{
    private Application $Application;

    private array $items;

    private string $label;

    private string $prompt;

    private bool $returnValue = false;

    public function __construct(Application $Application, array $items = [], ?string $prompt = null, ?string $label = null)
    {
        $this->Application = $Application;

        if (isset($label)) {
            $this->label = $label;
        }

        $this->setItems($items);
        
        if ($prompt) {
            $this->prompt = $prompt;
        } else {
            $this->prompt = 'Choose: ';
        }
    }

    public function getItems(): array
    {
        $first = reset($this->items);

        if (is_array($first)) {
            $items = array();
            array_walk_recursive($this->items, function($description, $command) use (&$items) {
                $items[$command] = $description;
            });
            return $items;
        }
        return $this->items;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getDescription(string $name): ?string
    {
        if (!array_key_exists($name, $this->getItems())) {
            return null;
        }

        return $this->getItems()[$name];
    }

    /**
     * @param string|null $prompt
     * @return mixed
     */
    public function prompt(string $prompt = null)
    {
        $input = $this->Application->prompt($prompt ?? $this->prompt);
        
        if (key($this->getItems()) === 0 && is_numeric($input) && (int) $input > 0) {
            $input = intval($input);
            $input--;
        }

        if (!$this->hasKey($input)) {
            return null;
        }

        if ($this->returnValue) {
            return $this->getValue($input);
        }

        return $input;
    }

    /**
     * @param string|int $key
     * @return bool
     */
    public function hasKey($key): bool
    {
        if (is_null($key)) return false;
        $items = $this->getItems();

        if (is_numeric($key)) {
            $key = intval($key);
            return array_key_exists($key, $items);
        }
        if (is_string($key)) {
            return array_key_exists($key, $items);
        }
        return false;
    }

    /**
     * @param string|int $key
     * @return mixed
     */
    public function getValue($key)
    {
        $items = $this->getItems();

        if (!$this->hasKey($key)) {
            throw new \InvalidArgumentException(sprintf('"%s" is an invalid option', $key));
        }
        foreach ($items as $_ => $value) {
            if ($key === $_) {
                return $value;
            }
        }
        foreach ($items as $_ => $value) {
            if ($key == $_) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Output the menu items
     */
    public function list(): self
    {
        $items = $this->items;

        // Increment array keys by 1 if 0-indexed
        if (key($items) === 0 && (!is_array($items[0]) || key($items[0]) === 0)) {
            $items = $this->reKey($items, function ($key) {
                return $key + 1;
            });
        }

        $first = reset($items);
        if (is_array($first)) {
            $rows = [];
            foreach ($items as $row_key => $row) {
                $rows[] = array_map(function ($command, $description) {
                    return sprintf(' [%s] %s', $command, $description);
                }, array_keys($row), $row);
            }

            $this->Application->table([], $rows, Table::borderPreset('none'))->print();
        } else {
            foreach ($items as $command => $description) {
                $this->Application->output->linef(' [%s] %s', $command, $description);
            }
        }

        return $this;
    }

    /**
     * @param array $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $label = $this->label ?? 'name';

        array_walk_recursive($items, function (&$description, $command) use ($label) {
            if (!is_scalar($description)) {
                if (is_object($description)) {
                    $description = $description->$label;
                } elseif (is_array($description)) {
                    $description = $description[$label];
                }
            }
        });

        $this->items = $items;

        return $this;
    }

    private function reKey(array $array, callable $callback)
    {
        // Multidemensional
        if (key($array) === 0 && is_array($array[0])) {
            foreach ($array as $top_key => $subarray) {
                $subarray = array_combine(
                    array_map($callback, array_keys($subarray)),
                    $subarray
                );
            }
        } else {
            $array = array_combine(
                array_map($callback, array_keys($array)),
                $array
            );
        }

        return $array;
    }

    /**
     * If true the menu prompt will return the item value instead of the key.
     *   $items = [0 => 'First', 1 => 'Second'];
     *   $this->prompt(): 1
     *   $this->setReturnValue(true);
     *   $this->prompt(): 'Second'
     * 
     * @param bool $bool
     * @return Menu
     */
    public function setReturnValue(bool $bool): self
    {
        $this->returnValue = $bool;
        return $this;
    }
}