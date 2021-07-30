<?php declare(strict_types=1);

namespace PhpCli\Git;

class Chunk {

    private string $diff;

    private array $old;

    private bool $no_newline = false;

    public function __construct(string $diff)
    {
        $this->setDiff($diff);
    }

    /**
     * Render and return a diff header string.
     *
     * @return string
     */
    public function getHeader(): string
    {
        $old_count = count($this->old);
        $new_count = count($this->new);
        $old_first_line = (array_key_first($this->old) ?? -1) + 1;
        $new_first_line = (array_key_first($this->new) ?? -1) + 1;

        return sprintf("@@ -%d,%d +%d,%d @@\n", $old_first_line, $old_count, $new_first_line, $new_count);
    }

    public function getOld(): array
    {
        return $this->old;
    }

    public function getNew(): array
    {
        return $this->new;
    }

    public function setDiff(string $diff): self
    {
        $this->diff = $diff;
        $this->parse();

        return $this;
    }

    public function setNoNewline(): self
    {
        $this->no_newline = true;

        return $this;
    }

    /**
     * Parse the diff/chunk string.
     *
     * @return self
     */
    private function parse(): self
    {
        $chunks = [
            'old' => [],
            'new' => []
        ];

        if (!empty($this->diff)) {
            $lines = explode("\n", $this->diff);
            $header = array_shift($lines);

            // @@ -from,hunk-no-of-lines-before +from,hunk-no-of-lines-after
            $startPos = strpos($header, '@', 1) + 2;
            $length = strpos($header, '@', $startPos) - $startPos;
            $header = substr($header, $startPos, $length);
            list($old, $new) = preg_split('/\s+/', $header, 2);
            $old = explode(',', substr($old, 1));
            $new = explode(',', substr($new, 1));

            foreach ($lines as $key => $line) {
                $action = substr($line, 0, 1); // '+', '-', or ' '
                $line = substr($line, 1);

                if ($line === ' No newline at end of file') {
                    $this->setNoNewline();
                }

                if ($action !== '-') {
                    $index = array_key_last($chunks['new']) ? array_key_last($chunks['new']) + 1 : $key + intval($new[0]) - 1;
                    $chunks['new'][$index] = $line;
                }

                if ($action !== '+') {
                    $index = array_key_last($chunks['old']) ? array_key_last($chunks['old']) + 1 : $key + intval($old[0]) - 1;
                    $chunks['old'][$index] = $line;
                }
            }

            // print "\nold: ".count($chunks['old']).$old[1]."\n";
            // print "\new: ".count($chunks['new']).$new[1]."\n";
            // assert(count($chunks['old']) === (int) $old[1]);
            // assert(count($chunks['new']) === (int) $new[1]);
        }

        $this->old = $chunks['old'];
        $this->new = $chunks['new'];

        return $this;
    }

    /**
     * Render the chunk diff
     *
     * @return string
     */
    public function __toString()
    {
        return $this->diff;
    }
}