<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Reporting\Logger;
use PhpCli\Reporting\Drivers\BufferLogger;

class Stage {

    private array $Files;

    private Repository $Repository;

    public function __construct(Repository $Repository)
    {
        $this->Repository = $Repository;
    }

    /**
     * Stage a pathspec to the working tree.
     * 
     * @param string|File $path
     * @return bool
     */
    public function add($path): bool
    {
        if ($path instanceof File) {
            return $this->addFile($path);
        }

        git::add($path);
        if (git::result() > 0) {
            throw new \Exception(sprintf('Error: pathspec \'%s\' did not match any files', $path));
        }

        return true;
    }

    private function addFile(File $File): bool
    {
        // $index_status = $File->getIndexStatus();
        // $worktree_status = $File->getWorktreeStatus();

        git::add($File->getPath());
        if (git::result() > 0) {
            throw new \Exception(sprintf('Error: pathspec \'%s\' did not match any files', $File->getPath()));
        }

        // Update instance meta data
        return $File->add();
    }

    /**
     * Discard local changes to the path spec.
     * 
     * @param string $path
     * @return bool
     */
    public function discard(string $path = null)
    {
        if ($path instanceof File) {
            return $this->discardFile($path);
        }

        if (version_compare(git::version(), '2.25', '>')) {
            $this->_log(git::restore($path));
        } else {
            $this->_log(git::checkout('--', $path));
        }

        return git::result() === 0;
    }

    /**
     * Discard local changes to the give File.
     * 
     * @param File $File
     * @return bool
     */
    public function discardFile(File $File): bool
    {
        return $this->discard($File->getPath());
    }

    /**
     * Get the staged files.
     *
     * @return array
     */
    public function getChanges(): array
    {
        $status = $this->Repository->getStatus();

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

    /**
     * Unstage the given pathspec from the working tree.
     * 
     * @param string|File $path
     */
    public function remove($path): bool
    {
        if ($path instanceof File) {
            return $this->removeFile($path);
        }

        // do not log, output is just a simplified git status
        git::reset('--', $path);

        return git::result() === 0;
    }

    /**
     * Unstage the given File from the working tree.
     * 
     * @param File $File
     */
    public function removeFile(File $File): bool
    {
        $this->_log(git::reset('--', $File->getPath()));

        return git::result() === 0;
    }

    private function _log(array $data, string $level = Logger::INFO)
    {
        if (count($data)) {
            $Logger = $this->Repository->Logger();
            $Logger->log(str_replace(["\t", "'"], [' ', ''], implode("\n", $data)), $level);

            if ($Logger instanceof BufferLogger) {
                $this->Repository->notify();
            }
        }
    }
}