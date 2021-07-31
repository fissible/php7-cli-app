<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class Diff {

    private ?array $chunks = null;

    private string $type;

    private string $original_path;

    private string $path;

    private string $header;

    public function __construct(string $path = null, string $type = null, array $chunks = null)
    {
        if ($path) {
            $this->setPath($path);
        }
        if ($type) {
            $this->setType($type);
        }
        if ($chunks) {
            $this->setChunks($chunks);
        }
    }

    public function header(): string
    {
        return $this->header;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function chunks()
    {
        return $this->chunks;
    }

    public function originalPath(): ?string
    {
        return $this->original_path ?? null;
    }

    public function setChunks(array $chunks): self
    {
        $this->chunks = $chunks;

        return $this;
    }

    public function setHeader(string $header): self
    {
        $this->header = $header;

        return $this;
    }

    public function setOriginalPath(string $path): self
    {
        $this->original_path = $path;

        return $this;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public static function parse(string $diff): ?Diff
    {
        if (empty($diff) || !Str::startsWith($diff, 'diff')) return null;

        $header = [];
        $Diff = new static();
        $lines = explode("\n", $diff);
        $filenames = str_replace('diff --git ', '', $lines[0]);
        $b_pos = strpos($filenames, 'b/');
        $path = substr($filenames, $b_pos + 2);
        $original_path = substr($filenames, 2, $b_pos - 3);
        $status = 'modified';
        $chunks = [];
        $diffs = [];

        $Diff->setPath($path);

        // collect header lines and parse status
        while (!empty($lines) && !Str::startsWith($lines[0], '@@')) {
            $line = array_shift($lines);
            $header[] = $line;

            if (false !== strpos($line, 'file mode')) {
                $status = preg_split('/\s+/', $line, 2)[0];
                if ($status === 'new') $status = 'added';
            } elseif (false !== strpos($line, 'rename from')) {
                $status = 'rename';
            }
        }

        $Diff->setType($status);

        if ($status === 'renamed' && $original_path) {
            $Diff->setOriginalPath($original_path);
        }

        $Diff->setHeader(implode("\n", $header));

        // Separate chunks
        if (!empty($lines) && substr($lines[0], 0, 2) === '@@') {
            $capture = [];
            foreach ($lines as $k => $line) {
                if (substr($line, 0, 2) === '@@') {
                    if (!empty($capture)) {
                        $chunks[] = $capture;
                        $capture = [];
                    }
                }
                $capture[] = $line;
            }
            if (!empty($capture)) {
                $chunks[] = $capture;
                $capture = [];
            }
        }

        if (!empty($chunks)) {
            $chunks = array_map(function ($chunk) {
                return new Chunk(implode("\n", $chunk));
            }, $chunks);
            $Diff->setChunks($chunks);
        }

        return $Diff;
    }

    /**
     * Parse diffs into File objects with discrete diff.
     *
     * @param array $lines
     * @return array
     */
    public static function parseFiles(array $lines): array
    {
        while (!empty($lines) && !Str::startsWith($lines[0], 'diff')) {
            array_shift($lines);
        }

        if (empty($lines)) {
            return [];
        }

        $i = -1;
        $files = [];
        foreach ($lines as $k => $line) {
            if (Str::startsWith($line, 'diff')) $i++;
            $files[$i][] = $line;
        }

        return array_filter(array_map(function ($lines) {
            if (empty($lines) || !Str::startsWith($lines[0], 'diff')) return null;

            $filenames = str_replace('diff --git ', '', $lines[0]);
            $path = substr($filenames, strpos($filenames, 'b/') + 2);
            $File = new File($path);

            return $File->parseDiff($lines);
        }, $files));
    }

    public function __toString()
    {
        $diff = '';

        if ($this->header) {
            $diff .= $this->header();
            $diff .= "\n";
        }

        if ($this->chunks) {
            $diff .= implode("\n", $this->chunks);
        }

        return $diff;
    }
}