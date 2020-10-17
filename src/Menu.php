<?php declare(strict_types=1);

namespace PhpCli;

class Menu
{
    private Application $app;

    private array $items;

    private string $label;

    private string $prompt;

    public function __construct(Application $app, array $items = [], string $label = 'name', ?string $prompt = null)
    {
        $this->app = $app;
        $this->items = $items;
        $this->label = $label;

        if (isset($prompt)) {
            $this->prompt = $prompt;
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
     * @param bool $getKey
     * @return mixed
     */
    public function prompt(string $prompt = null, bool $getKey = true)
    {
        $input = $this->app->prompt($prompt ?? $this->prompt);
        $numeric = is_numeric($input);

        if (array_key_exists($input, $this->items) || ($numeric && array_key_exists((int) $input, $this->items))) {
            if ($input !== null) {
                foreach ($this->items as $key => $value) {
                    // 1 == '1'
                    if (is_int($key) && $numeric && is_string($input)) {
                        $input = (int) $input;
                    }
                    if ($key === $input) {
                        return $value;
                    }
                }
            }
        }
        return null;
    }

    /*
    public function prompt(string $prompt): string
    {
        $this->input = readline($prompt);
        return $this->input;
    }
    */

    /**
     * Output the menu items
     */
    public function list(): void
    {
        $label = $this->label ?? 'name';
        foreach ($this->items as $command => $description) {
            if (!is_scalar($description)) {
                if (is_object($description)) {
                    $description = $description->$label;
                } elseif (is_array($description)) {
                    $description = $description[$label];
                }
            }
            $this->app->output->linef(' [%s] %s', $command, $description);
        }
    }
}