<?php declare(strict_types=1);

namespace PhpCli;

use Ds\Vector;
use PhpCli\Arr;

class Grid
{
    private Vector $data;

    private int $height;

    private int $width;

    private $empty;

    private array $pointer;

    /**
     * @param array $array
     * @param mixed $emptyCell
     */
    public function __construct(iterable $array = [], $emptyCell = null)
    {
        $this->empty = $emptyCell;

        if (!empty($array)) {
            $this->setData($array);
        }
    }

    /**
     * Create and return a filled grid
     *
     * @param integer $width
     * @param integer|null $height
     * @param mixed $fill
     * @return Matrix<Vector<Vector<mixed>>>
     */
    public static function create(int $width,  ?int $height = null, $fill = null): Grid
    {
        if (is_null($height)) {
            $height = $width;
        }
        $rows = new Vector();
        $rows->allocate($height);
        $y = 0;
        while ($y < $height) {
            $row = new Vector();
            $row->allocate($width);
            $x = 0;
            while ($x < $width) {
                $row->push($fill);
                $x++;
            }
            $rows->push($row);
            $y++;
        }
        return new self($rows);
    }

    /**
     * @param int $x
     * @return Vector
     */
    public function column(int $x): Vector
    {
        if ($x + 1 > $this->width) {
            throw new \RangeException(sprintf('%d: invalid column offest', $x));
        }

        $column = new Vector();
        $column->allocate($this->height);

        foreach ($this->data as $row) {
            foreach ($row as $_x => $cell) {
                if ($_x === $x) {
                    $column->push($cell);
                }
            }
        }

        return $column;
    }

    /**
     * Move the pointer up.
     * 
     * @param int $move
     * @return self
     */
    public function up(int $move = 1): self
    {
        $new = $this->pointer[0] - $move;
        if ($new < 0) {
            throw new \RangeException(sprintf('%d: invalid row offest (up)', $new));
        }

        $this->pointer[0] = $new;

        return $this;
    }

    /**
     * Move the pointer down.
     * 
     * @param int $move
     * @return self
     */
    public function down(int $move = 1): self
    {
        $new = $this->pointer[0] + $move;
        if ($new > $this->height) {
            throw new \RangeException(sprintf('%d: invalid row offest (down)', $new));
        }

        $this->pointer[0] = $new;

        return $this;
    }

    /**
     * Move the pointer left.
     * 
     * @param int $move
     * @return self
     */
    public function left(int $move = 1): self
    {
        $new = $this->pointer[1] - $move;
        if ($new < 0) {
            throw new \RangeException(sprintf('%d: invalid column offest (left)', $new));
        }

        $this->pointer[1] = $new;

        return $this;
    }

    /**
     * Move the pointer right.
     * 
     * @param int $move
     * @return self
     */
    public function right(int $move = 1): self
    {
        $new = $this->pointer[1] + $move;
        if ($new > $this->width) {
            throw new \RangeException(sprintf('%d: invalid column offest (right)', $new));
        }

        $this->pointer[1] = $new;

        return $this;
    }

    /**
     * Get the value at the pointer coordinates.
     * 
     * @return mixed
     */
    public function peek()
    {
        return $this->get($this->pointer[0], $this->pointer[1]);
    }

    /**
     * Get the current pointer coordinates. Optionally set them to new values.
     * 
     * @param int|null $y
     * @param int|null $x
     * @return array
     */
    public function pointer(int $y = null, int $x = null): array
    {
        $current = $this->pointer;

        if (!is_null($y)) {
            if ($y < 0 || $y > $this->height) {
                throw new \RangeException(sprintf('%d: invalid column offest (height: %d)', $y, $this->height));
            }
            $this->pointer[0] = $y;
        }

        if (!is_null($x)) {
            if ($x < 0 || $x > $this->width) {
                throw new \RangeException(sprintf('%d: invalid row offest (width: %d)', $x, $this->width));
            }
            $this->pointer[1] = $x;
        }

        return $current;
    }

    /**
     * Find a value in the Grid and return the coordinates at which it was found.
     * 
     * @param mixed $value
     * @return array|null
     */
    public function find($value): ?array
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('Query must be a scalar.');
        }

        $value = $value . '';

        if (strlen($value) < 1) {
            return null;
        }

        $coords = null;
        $pointer = 0;
        $_char = $value[0];
        $matching = false;

        foreach ($this->data as $y => $row) {
            foreach ($row as $x => $char) {
                if ($char === $_char) {
                    if (!$matching) {
                        $matching = true;
                        $coords = [$y, $x];
                    }
                    $pointer++;
                    if (isset($value[$pointer])) {
                        $_char = $value[$pointer];
                    } else {
                        break(2);
                    }
                } else {
                    $coords = null;
                    $pointer = 0;
                    $_char = $value[0];
                    $matching = false;
                }
            }
        }

        return $coords;
    }

    /**
     * @param int $y
     * @param int $x
     * @return mixed
     */
    public function get(int $y, int $x = null)
    {
        if ($y + 1 > $this->height) {
            throw new \RangeException(sprintf('%d: invalid row offest', $y));
        }

        if ($x + 1 > $this->width) {
            throw new \RangeException(sprintf('%d: invalid column offest', $x));
        }

        return $this->data[$y][$x];
    }

    /**
     * @return Vector
     */
    public function getData(): Vector
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function height(): int
    {
        return $this->height;
    }

    /**
     * Set the internal pointer to the top-left.
     * 
     * @return self
     */
    public function rewind(): self
    {
        $this->pointer = [0, 0];

        return $this;
    }

    /**
     * @param int $y
     * @return Vector
     */
    public function row(int $y): Vector
    {
        if ($y + 1 > $this->height) {
            throw new \RangeException(sprintf('%d: invalid row offest', $y));
        }

        return $this->data->get($y);
    }

    /**
     * @param int $y
     * @param int $x
     * @return void
     */
    public function set(int $y, int $x = null, $value = null): void
    {
        if ($y + 1 > $this->height) {
            throw new \RangeException(sprintf('%d: invalid row offest', $y));
        }

        if ($x + 1 > $this->width) {
            throw new \RangeException(sprintf('%d: invalid column offest', $x));
        }

        $this->data[$y][$x] = $value ?? $this->empty;
    }

    /**
     * @param array $array
     * @return self
     */
    public function setData(iterable $array): self
    {
        $this->validate($array);
        $this->height = count($array);
        $this->width = count($array[0]);
        $this->data = new Vector();
        $this->data->allocate($this->height);

        foreach ($array as $_array) {
            $c = count($_array);
            if ($c > $this->width) {
                $this->width = $c;
            }
        }

        foreach ($array as $row) {
            if (is_array($row)) {
                $this->data->push(new Vector(
                    array_pad($row, $this->width, $this->empty)
                ));
            } else {
                $this->data->push($row);
            }
        }

        $this->rewind();

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->data->toArray();
    }

    public function valid(int $y, int $x = null): bool
    {
        if ($y + 1 > $this->height) {
            return false;
        }

        if ($x + 1 > $this->width) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function width(): int
    {
        return $this->width;
    }

    private function validate(iterable $array)
    {
        if ($array instanceof Vector) {
            $array = $array->toArray();
        }

        $array = array_map(function ($_array) {
            if ($_array instanceof Vector) return $_array->toArray();
            return $_array;
        }, (array) $array);

        if (!Arr::isIndexed($array)) {
            throw new \InvalidArgumentException('Grid must be initialized with an indexed array.');
        }

        if (!Arr::isNested($array)) {
            throw new \InvalidArgumentException('Grid must be initialized with an indexed array of arrays.');
        }

        foreach ($array as $_array) {
            if (!Arr::isIndexed($_array)) {
                throw new \InvalidArgumentException('Grid must be initialized with an indexed array of indexed arrays.');
            }
        }
    }
}