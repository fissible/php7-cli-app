<?php declare(strict_types=1);

namespace PhpCli\Git;

class Branch {

    private string $name;

    private Branch $mergeTarget;

    private Branch $pushTarget;

    private ?string $status;

    private bool $tracked;

    public function __construct(string $name, bool $tracked = false, string $status = null)
    {
        $this->name = $name;
        $this->tracked = $tracked;
        $this->status = $status;
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

    public function delete()
    {
        $output = git::branch('--delete', $this->name);

        print_r($output);
        /*

        */

        return git::result() === 0;
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