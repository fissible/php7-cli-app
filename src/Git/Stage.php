<?php declare(strict_types=1);

namespace PhpCli\Git;

class Stage {

    private array $Files;

    private Repository $Repository;

    public function __construct(Repository $Repository)
    {
        $this->Repository = $Repository;
    }

    public function add($file): bool
    {
        if ($file instanceof File) {
            return $this->addFile($file);
        }

        git::add($file);
        if (git::result() > 0) {
            throw new \Exception(sprintf('Error: pathspec \'%s\' did not match any files', $file));
        }

        return true;
    }

    private function addFile(File $File): bool
    {
        $index_status = $File->getIndexStatus();
        $worktree_status = $File->getWorktreeStatus();

        git::add($File->getPath());
        if (git::result() > 0) {
            throw new \Exception(sprintf('Error: pathspec \'%s\' did not match any files', $File->getPath()));
        }

        return $File->add();
    }

    /**
     * Get the staged files.
     *
     * @return array
     */
    public function getChanges(): array
    {
        $status = $this->Repository->status();

        return array_filter(array_merge(
            // 'Changes to be committed:' 
            $status[Repository::STR_CHANGES_TO_BE_COMMITTED],
            // 'Changes not staged for commit:'
            $status[Repository::STR_CHANGES_NOT_STAGED]
        ));
    }

    /**
     * @todo distinguish between staged/unstaged?
     * Check if there are unstaged changes.
     *
     * @return boolean
     */
    public function hasChanges(): bool
    {
        $changes = $this->getChanges();

        return count($changes) > 0;
    }
}