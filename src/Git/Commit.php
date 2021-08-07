<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class Commit {

    private int $ahead;

    private int $behind;

    private string $hash;

    private bool $loaded = false;

    private ?string $message;

    private Author $Author;

    private ?\DateTime $authorDate;

    private ?\DateTime $Date;

    private Author $Committer;

    private ?\DateTime $committerDate;

    private array $Files;

    private Commit $Parent;

    public function __construct(string $hash, string $message = null)
    {
        $this->hash = $hash;
        $this->message = $message;
    }

    public function getAuthor(): ?Author
    {
        $this->load();
        return $this->Author ?? null;
    }

    public function getAuthorDate(): ?\DateTime
    {
        $this->load();
        return $this->authorDate ?? null;
    }

    public function getCommitter(): ?Author
    {
        $this->load();
        return $this->Committer ?? null;
    }

    public function getCommitterDate(): ?\DateTime
    {
        $this->load();
        return $this->committerDate ?? null;
    }

    public function getDate(): ?\DateTime
    {
        $this->load();
        return $this->Date ?? null;
    }

    public function getFiles(): array
    {
        $this->load();
        return $this->Files ?? [];
    }

    public function getHash(int $length = 0): string
    {
        return $length ? substr($this->hash, 0, $length) : $this->hash;
    }

    public function getMessage(): ?string
    {
        return $this->message ?? null;
    }

    public function getParent(): ?Commit
    {
        $this->load();
        return $this->Parent ?? null;
    }

    public function isDetached(): bool
    {
        $output = git::rev_parse('--abbrev-ref', 'HEAD');

        return $output[0] === '* (no branch)';
    }

    /**
     * Parse a git log entry for this commit.
     *
     * @return self
     */
    public function load(bool $force = false): self
    {
        if (!$force || !$this->loaded) {
            try {
                $output = git::show($this->hash, '--format=raw');
            } catch (\Exception $e) {
                if (Str::startsWith($e->getMessage(), 'Error: ambiguous commit hash')) {
                    throw new \Exception(sprintf('Error: ambiguous commit hash \'%s\'', $this->hash));
                }
                throw $e;
            }

            $diffs = [];
            $headerLines = [];

            while (false === strpos($output[0], 'diff --git')) {
                $headerLines[] = array_shift($output);
            }

            $info = static::parseHeader($headerLines);

            if (isset($info['parent'])) {
                $this->setParent(new Commit($info['parent']));
            }

            if (isset($info['author'])) {
                $this->setAuthor(Author::parse($info['author']), $info['authorDate'] ?? null);
            }

            if (isset($info['committer'])) {
                $this->setCommitter(Author::parse($info['committer']), $info['committerDate'] ?? null);
            }

            if (isset($info['date'])) {
                $this->setDate($info['date']);
            }

            if (isset($info['message'])) {
                $this->setMessage($info['message']);
            }

            $this->setFiles(Diff::parseFiles($output));

            $this->loaded = true;
        }

        return $this;
    }

    public function setAheadBehind(int $ahead, int $behind): self
    {
        $this->ahead = $ahead;
        $this->behind = $behind;

        return $this;
    }

    public function setAuthor(Author $Author, ?\DateTime $Date): self
    {
        $this->Author = $Author;

        if ($Date) {
            $this->authorDate = $Date;
        }

        return $this;
    }

    public function setCommitter(Author $Committer, ?\DateTime $Date = null): self
    {
        $this->Committer = $Committer;

        if ($Date) {
            $this->committerDate = $Date;
        }

        return $this;
    }

    public function setDate(\DateTime $Date): self
    {
        $this->Date = $Date;

        return $this;
    }

    public function setFiles(array $Files): self
    {
        $this->Files = $Files;

        return $this;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function setParent(Commit $Commit): self
    {
        $this->Parent = $Commit;

        return $this;
    }

    /**
     * Return a Commit object for the supplied hash string.
     *
     * @param string $hash
     * @return Commit|null
     */
    public static function get(string $hash): ?Commit
    {
        $Commit = new static($hash);
        
        return $Commit->load();
    }

    /**
     * Parse the commit header information.
     *
     * @param array $lines
     * @return array
     */
    private static function parseHeader(array $lines): array
    {
        $info = [
            'hash' => null,
            'author' => null,
            'authorDate' => null,
            'date' => null,
            'committer' => null,
            'committerDate' => null,
            'message' => null,
            'parent' => null,
            'tree' => null
        ];

        foreach ($lines as $i => $line) {
            if (empty($line)) continue;
            if (Str::startsWith($line, 'diff')) break;

            if (is_null($info['hash']) && Str::startsWith($line, 'commit ')) {
                $info['hash'] = preg_split('/\s+/', $line, 2)[1];

/*
❯ git show 7ba34821fb3b31b67333184ee0ac4ae5c09f8089 --format=raw
commit 7ba34821fb3b31b67333184ee0ac4ae5c09f8089
tree 5bc4f202b46af52617c0cb6cec9d9f14929c5531
parent bbe143f9972bf306e7d08f49a0c1a1c7937171ec
author Allen McCabe <amccabe@csatf.org> 1624473285 -0700
committer Allen McCabe <amccabe@csatf.org> 1624473285 -0700

    Update git to cd into repo dir.

diff --git a/src/git.php b/src/git.php
index d126854..f5c7cfe 100644
--- a/src/git.php
+++ b/src/git.php
*/

                // trim "(HEAD -> master)" or other branch notation
                $info['hash'] = trim(Str::before($info['hash'], '('));
            } elseif (is_null($info['tree']) && Str::startsWith($line, 'tree ')) {
                $info['tree'] = preg_split('/\s+/', $line, 2)[1];
            } elseif (is_null($info['parent']) && Str::startsWith($line, 'parent ')) {
                $info['parent'] = preg_split('/\s+/', $line, 2)[1];
            } elseif (is_null($info['author']) && Str::startsWith($line, 'author ')) {
                $author = Str::lprune($line, 'author ');
                $author = trim(Str::before($author, '<'));
                $email = Str::capture($line, '<', '>');

                $info['author'] = sprintf('%s <%s>', $author, $email);
                $info['date'] = $info['authorDate'] = \DateTime::createFromFormat('U T', trim(Str::after($line, '>')));
            } elseif (is_null($info['committer']) && Str::startsWith($line, 'committer ')) {
                $author = Str::lprune($line, 'committer ');
                $author = trim(Str::before($author, '<'));
                $email = Str::capture($line, '<', '>');

                $info['committer'] = sprintf('%s <%s>', $author, $email);
                $info['committerDate'] = \DateTime::createFromFormat('U T', trim(Str::after($line, '>')));
            } elseif (is_null($info['author']) && Str::startsWith($line, 'Author:')) {
                $info['author'] = preg_split('/\s+/', $line, 2)[1];
            } elseif (is_null($info['authorDate']) && substr($line, 0, 7) === 'AuthorDate:') {
                $info['authorDate'] = preg_split('/\s+/', $line, 2)[1];
                $info['authorDate'] = \DateTime::createFromFormat(git::DATE_FORMAT, $info['authorDate']);
            } elseif (is_null($info['committer']) && substr($line, 0, 7) === 'Commit:') {
                $info['committer'] = preg_split('/\s+/', $line, 2)[1];
            } elseif (is_null($info['committerDate']) && substr($line, 0, 7) === 'CommitDate:') {
                $info['committerDate'] = preg_split('/\s+/', $line, 2)[1];
                $info['committerDate'] = \DateTime::createFromFormat(git::DATE_FORMAT, $info['committerDate']);
            } elseif (is_null($info['date']) && substr($line, 0, 5) === 'Date:') {
                $info['date'] = preg_split('/\s+/', $line, 2)[1];
                $info['date'] = \DateTime::createFromFormat(git::DATE_FORMAT, $info['date']);
            } elseif (is_null($info['message']) && 0 === strpos($line, '    ')) {
                $info['message'] = trim($line);
            } elseif (isset($info['message']) && !empty($line)) {
                $info['message'] .= "\n".trim($line);
            }
        }

        return $info;
    }
}
