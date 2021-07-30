<?php declare(strict_types=1);

namespace PhpCli\Git;

class File {

    private string $path;

    private string $repository_path;

    private string $original_path;

    private string $worktree_status;

    private ?string $index_status;

    private Diff $Diff;

    private array $file_mode = [
        'HEAD' => null,
        'index' => null,
        'worktree' => null,
        'stage1' => null,
        'stage2' => null,
        'stage3' => null,
        'worktree' => null
    ];

    private array $object_name = [
        'HEAD' => null,
        'index' => null,
        'stage1' => null,
        'stage2' => null,
        'stage3' => null
    ];

    public function __construct(string $path, $worktree_status = 'untracked', $index_status = null)
    {
        $this->path = $path;
        $this->setWorktreeStatus($worktree_status);
        $this->setIndexStatus($index_status);
    }

    public function add(): bool
    {
        $index_status = $this->getIndexStatus();
        $worktree_status = $this->getWorktreeStatus();

        if ($index_status === 'untracked' && $worktree_status === 'untracked') {
            $this->setIndexStatus('added');
            $this->setWorktreeStatus('unmodified');

            return true;
        }

        if ($worktree_status === 'modified') {
            $this->setWorktreeStatus('unmodified');

            return true;
        }

        if ($index_status === 'unmodified' && $worktree_status === 'deleted') {
            $this->setIndexStatus('deleted');
            $this->setWorktreeStatus('unmodified');

            return true;
        }

        if ($index_status === 'untracked' && $worktree_status === 'untracked') {
            $this->setIndexStatus('deleted');
            $this->setWorktreeStatus('unmodified');

            return true;
        }

        return false;
    }

    public function addDiff(Diff $Diff): self
    {
        $this->Diff = $Diff;

        return $this;
    }

    public function diff(): ?Diff
    {
        if (!isset($this->Diff)) {
            $this->parseDiff();
        }

        return $this->Diff ?? null;
    }

    public function getIndexStatus(): ?string
    {
        return $this->index_status;
    }

    public function getOriginalPath(): ?string
    {
        if (isset($this->original_path)) {
            return $this->original_path;
        }

        return null;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getWorktreeStatus(): string
    {
        return $this->worktree_status;
    }

    public function isIgnored(): bool
    {
        return $this->index_status === 'ignored';
    }

    public function isModified(): bool
    {
        return $this->index_status === 'unmodified' && $this->worktree_status === 'modified';
    }

    public function isNew(): bool
    {
        return !$this->isTracked();
    }

    public function isTracked(): bool
    {
        return $this->index_status !== 'untracked';
    }

    /**
     * Invoke git diff and parse the response.
     *
     * @return self
     */
    public function parseDiff(array $lines = []): self
    {
        if (empty($lines)) {
            $lines = git::diff($this->path);
        }

        if (!empty($lines)) {
            $this->addDiff(Diff::parse(implode("\n", $lines)));
        }

        $status = $this->Diff->type();

        $this->setWorktreeStatus($status);

        $original_path = $this->Diff->originalPath();

        if ($status === 'renamed' && $original_path) {
            $this->setOriginalPath($original_path);
        }

        return $this;
    }

    /**
     * Get a git status like string for this file.
     *
     * @return string
     */
    public function renderStatus(): string
    {
        // return $this->index_status.'/'.$this->worktree_status.': '.$this->getPath();
        $status = '';

        switch ($this->worktree_status) {
            case 'untracked':
                break;
            case 'added':
                $status = 'new file:   ';
                break;
            case 'deleted':
                if ($this->worktree_status === 'updated but unmerged') {
                    $status = 'deleted by us:   ';
                }
                break;
            default:
                $status = $this->worktree_status.':   ';
                break;
        }

        $status .= $this->getPath();

        return $status;
    }

    public function setFileMode(string $mode, string $target = 'index'): self
    {
        if (!is_numeric($mode)) {
            throw new \InvalidArgumentException();
        }

        if (!in_array($target, array_keys($this->file_mode))) {
            throw new \InvalidArgumentException();
        }

        $this->file_mode[$target] = $mode;

        return $this;
    }

    public function setIndexStatus(?string $status = null): self
    {
        $this->index_status = $status;

        return $this;
    }

    public function setObjectName(string $name, string $target): self
    {
        if (!in_array($target, array_keys($this->object_name))) {
            throw new \InvalidArgumentException();
        }

        $this->object_name[$target] = $name;

        return $this;
    }

    public function setOriginalPath(string $path): self
    {
        $this->original_path = $path;

        return $this;
    }

    public function setRepositoryPath(string $path): self
    {
        $this->repository_path = $path;

        return $this;
    }

    public function setWorktreeStatus(string $status): self
    {
        $this->worktree_status = $status;

        return $this;
    }

    /**
     * Delete the file.
     *
     * @return boolean
     */
    private function delete(): bool
    {
        $this->validateFullPath();

        return unlink($this->repository_path.$this->path);
    }

    private function validateFullPath()
    {
        if (!isset($this->repository_path)) {
            throw new \Exception('File does not have the repository path configured');
        }
    }

    /**
     * Undocumented function
     *
     * @param string $content
     * @param boolean $append
     * @return int
     */
    private function write(string $content, bool $append = false): int
    {
        $this->validateFullPath();

        $filename = $this->repository_path.$this->path;
        
        return (int) file_put_contents($filename, $content, $append ? FILE_APPEND : 0);
    }

    public static function parseStatus(string $status_line)
    {
        $modes = [
            '000000' => null,
            '040000' => 'dir',
            '100644' => 'file',
            '100755' => 'exe',
            '120000' => 'symlink'
        ];
        $statuses = [
            '.' => 'unmodified',
            'M' => 'modified',
            'A' => 'added',
            'D' => 'deleted',
            'R' => 'renamed',
            'C' => 'copied',
            'U' => 'updated but unmerged'
        ];
        $info = [];

        $normalizeObjectName = function ($name) {
            return $name === '0000000000000000000000000000000000000000' ? null : $name;
        };

        // if (false !== strpos($status_line, 'tmp_text.txt')) {
        //     print "\n[==] ".$status_line;
        //     // print "\n[==] "
        //     print "\n";
        // }

        switch ($status_line[0]) {
            case '!':
                $info = [
                    'path' => trim(substr($status_line, 2)),
                    'worktree_status' => 'ignored',
                    'index_status' => 'ignored'
                ];
            break;
            case '?':
                $info = [
                    'path' => trim(substr($status_line, 2)),
                    'worktree_status' => 'untracked',
                    'index_status' => 'untracked'
                ];
            break;
            case '1':
                // 1 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <path>
                list(
                    $status_codes,
                    $submodule_state,
                    $HEAD_mode,
                    $index_mode,
                    $worktree_mode,
                    $HEAD_object_name,
                    $index_object_name,
                    $path
                ) = explode(' ', substr($status_line, 2));

                $info = [
                    'path' => $path,
                    'worktree_status' => $statuses[$status_codes[1]],
                    'index_status' => $statuses[$status_codes[0]],
                    'HEAD_file_mode' => $HEAD_mode,
                    'index_file_mode' => $index_mode,
                    'worktree_file_mode' => $worktree_mode,
                    'HEAD_file_type' => $modes[$HEAD_mode],
                    'index_file_type' => $modes[$index_mode],
                    'worktree_file_type' => $modes[$worktree_mode],
                    'HEAD_object_name' => $normalizeObjectName($HEAD_object_name),
                    'index_object_name' => $normalizeObjectName($index_object_name),
                    'original_path' => null
                ];
            break;
            case '2':
                // 2 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <X><score> <path><sep><origPath>
                list(
                    $status_codes,
                    $submodule_state,
                    $HEAD_mode,
                    $index_mode,
                    $worktree_mode,
                    $HEAD_object_name,
                    $index_object_name,
                    $rename_or_copy_score,
                    $path_original_path
                ) = explode(' ', substr($status_line, 2));
                list($path, $original_path) = explode("\t", $path_original_path);

                $info = [
                    'path' => $path,
                    'worktree_status' => $statuses[$status_codes[1]],
                    'index_status' => $statuses[$status_codes[0]],
                    'HEAD_file_mode' => $HEAD_mode,
                    'index_file_mode' => $index_mode,
                    'worktree_file_mode' => $worktree_mode,
                    'HEAD_file_type' => $modes[$HEAD_mode],
                    'index_file_type' => $modes[$index_mode],
                    'worktree_file_type' => $modes[$worktree_mode],
                    'HEAD_object_name' => $normalizeObjectName($HEAD_object_name),
                    'index_object_name' => $normalizeObjectName($index_object_name),
                    'original_path' => $original_path
                ];
            break;
            case 'u':
                list(
                    $status_codes,
                    $submodule_state,
                    $stage1_mode,
                    $stage2_mode,
                    $stage3_mode,
                    $worktree_mode,
                    $stage1_object_name,
                    $stage2_object_name,
                    $stage3_object_name,
                    $path
                ) = explode(' ', substr($status_line, 2));

                $info = [
                    'path' => $path,
                    'worktree_status' => $statuses[$status_codes[1]],
                    'index_status' => $statuses[$status_codes[0]],
                    'stage1_mode' => $stage1_mode,
                    'stage2_mode' => $stage2_mode,
                    'stage3_mode' => $stage3_mode,
                    'worktree_mode' => $worktree_mode,
                    'stage1_object_name' => $stage1_object_name,
                    'stage2_object_name' => $stage2_object_name,
                    'stage3_object_name' => $stage3_object_name,
                ];
            break;
        }

        return $info;
    }
}