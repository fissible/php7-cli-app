<?php declare(strict_types=1);

namespace PhpCli;

class Menu
{
    private Application $Application;

    private array $items;

    private string $label;

    private string $prompt;

    private bool $returnValue = false;

    public function __construct(Application $Application, array $items = [], ?string $prompt = 'Choose: ', ?string $label = null)
    {
        $this->Application = $Application;
        $this->setItems($items);
        
        if ($prompt) {
            $this->prompt = $prompt;
        }

        if (isset($label)) {
            $this->label = $label;
        }
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getDescription(string $name): ?string
    {
        if (!array_key_exists($name, $this->items)) {
            return null;
        }

        return $this->items[$name];
    }

    /**
     * @param string|null $prompt
     * @return mixed
     */
    public function prompt(string $prompt = null)
    {
        $input = $this->Application->prompt($prompt ?? $this->prompt);
        
        if (key($this->items) === 0 && is_numeric($input) && (int) $input > 0) {
            $input = intval($input);
            $input--;
        }

        try {
            if (!$this->returnValue && $this->hasKey($input)) {
                return $input;
            }
            return $this->getValue($input);
        } catch (\InvalidArgumentException $e) {
            //
        }

        return null;
    }

    /**
     * @param string|int $key
     * @return bool
     */
    public function hasKey($key): bool
    {
        if (!is_null($key) && array_key_exists(strtolower($key), $this->items)) {
            return true;
        }
        if (is_numeric($key) && array_key_exists((int)$key, $this->items)) {
            return true;
        }
        return false;
    }

    /**
     * @param string|int $key
     * @return mixed
     */
    public function getValue($key)
    {
        foreach ($this->items as $_ => $value) {
            if ($this->hasKey($key)) {
                return $value;
            }
        }
        throw new \InvalidArgumentException(sprintf('"%s" is an invalid option', $key));
    }

    /**
     * Output the menu items
     */
    public function list(): self
    {
        $label = $this->label ?? 'name';
        $items = $this->items;

        // Increment array keys by 1 if 0-indexed
        if (key($items) === 0) {
            $items = array_combine(
                array_map(function ($key) { return ++$key; }, array_keys($items)),
                $items
            );
        }

        foreach ($items as $command => $description) {
            if (!is_scalar($description)) {
                if (is_object($description)) {
                    $description = $description->$label;
                } elseif (is_array($description)) {
                    $description = $description[$label];
                }
            }
            $this->Application->output->linef(' [%s] %s', $command, $description);
        }

        return $this;
    }

    /**
     * @param array $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = [];
        foreach ($items as $key => $value) {
            if (!is_numeric($key) && is_string($key)) {
                $key = strtolower($key);
            }
            $this->items[$key] = $value;
        }
        return $this;
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