<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class Branch {

    private string $name;

    private int $ahead = 0;

    private int $behind = 0;

    private bool $checkedOut;

    private bool $isHEAD = false;

    private Branch $mergeTarget;

    private Branch $pushTarget;

    private ?string $status;

    private bool $tracked;

    private Remote $Remote;

    public function __construct(string $name, bool $tracked = false, bool $checkedOut = false)
    {
        $this->name = $name;
        $this->tracked = $tracked;
        $this->checkedOut = $checkedOut;
    }

    public function ahead(): int
    {
        return $this->ahead;
    }

    public function behind(): int
    {
        return $this->behind;
    }

    public function branch(string $name)/*: Branch*/
    {
        /*
        git branch <branch>
        git checkout <branch>
        - OR -
        git checkout -b <branch>
        */
        $output = git::branch($name);

        print_r($output);
        /*

        */

        return git::result() === 0;
    }

    public function delete(bool $force = false)
    {
        return git::branch($force ? '-D' : '-d', $this->name);
    }

    public function getCommitsAheadBehind()
    {
        $Remote = $this->Remote();
        if (is_null($Remote)) {
            return null;
        }

        $remoteBranchName = $this->name;
        if (isset($this->mergeTarget)) {
            $remoteBranchName = $this->mergeTarget->name();
        } elseif (isset($this->pushTarget)) {
            $remoteBranchName = $this->pushTarget->name();
        }

        $args = ['--left-right', '--count', sprintf('%s...%s/%s', $this->name, $Remote->name(), $remoteBranchName)];
        // git rev-list --left-right --count master...origin/master

        if ($output = git::rev_list(...$args)) {
            [$ahead, $behind] = preg_split('/\s+/', trim($output[0]), 2);
            
            return compact('ahead', 'behind');
        }
        return null;
    }

    public function isCheckedOut(): bool
    {
        return $this->checkedOut;
    }

    public function isHEAD(): bool
    {
        return $this->isHEAD;
    }

    public function isLocal(): bool
    {
        return !$this->isRemote();
    }

    public function isRemote(): bool
    {
        return isset($this->Remote) && Str::startsWith($this->name, $this->Remote->name());
    }

    public function isTracked(): bool
    {
        return $this->tracked;
    }

    public function mergesTo(): ?Branch
    {
        return $this->mergeTarget ?? null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function pushesTo(): ?Branch
    {
        return $this->pushTarget ?? null;
    }

    public function Remote(): ?Remote
    {
        return $this->Remote ?? null;
    }

    public function setAhead(int $ahead): self
    {
        $this->ahead = $ahead;

        return $this;
    }

    public function setBehind(int $behind): self
    {
        $this->behind = $behind;

        return $this;
    }

    public function setCheckedOut(bool $checkedOut): self
    {
        $this->checkedOut = $checkedOut;

        return $this;
    }

    public function setIsHead(bool $is = true): self
    {
        $this->isHEAD = $is;

        return $this;
    }

    public function setMergeTo(Branch $branch): self
    {
        $this->mergeTarget = $branch;

        return $this;
    }

    public function setPushTo(Branch $branch): self
    {
        $this->pushTarget = $branch;

        return $this;
    }

    public function setRemote(Remote $Remote): self
    {
        $this->Remote = $Remote;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setTracked(bool $tracked = true): self
    {
        $this->tracked = $tracked;

        return $this;
    }

    public function status(): ?string
    {
        return $this->status ?? null;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}