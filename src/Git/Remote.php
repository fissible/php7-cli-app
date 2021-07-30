<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class Remote {

    private string $name;

    private ?string $fetchUrl;

    private ?string $pushUrl;

    private string $HEAD_branch;

    private array $branches;

    public function __construct(string $name, ?string $url = null)
    {
        $this->name = $name;
        $this->setFetchUrl($url);
        $this->setPushUrl($url);
    }

    /**
     * Add a remote repository.
     *
     * @param string $name
     * @param string $url
     * @return bool
     */
    public function add(bool $fetch = false, bool $tags = false): bool
    {
        $flag = $tags ? '--tags' : null;
        $flag ??= $fetch ? '-f' : null;

        git::remote('add', $this->name, $this->url, $flag);

        return git::result() === 0;
    }

    public function branch(string $name, bool $refresh = false): ?Branch
    {
        if (!isset($this->branches) || $refresh) {
            $this->show();
        }

        if (isset($this->branches[$name])) {
            return $this->branches[$name];
        }

        return null;
    }

    public function branches(bool $refresh = false): array
    {
        if (!isset($this->branches) || $refresh) {
            $this->show();
        }
        return $this->branches;
    }

    /**
     * Downloads the data to your local repository — it doesn’t 
     * automatically merge it with any of your work or modify 
     * what you’re currently working on
     *
     * @return boolean
     */
    public function fetch(bool $prune = false): bool
    {
        $prune = $prune ? '--prune' : null;

        git::fetch($this->name, $prune);

        return git::result() === 0;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getFetchUrl(): ?string
    {
        return $this->fetchUrl;
    }

    public function getPushUrl(): ?string
    {
        return $this->pushUrl;
    }

    public function hasBranch($branch): bool
    {
        if ($branch instanceof Branch) {
            $branch = $branch->name();
        } elseif(!is_string($branch)) {
            throw new \InvalidArgumentException();
        }

        if (isset($this->branches)) {
            return isset($this->branches[$branch]);
        }

        // git ls-remote --heads git@github.com:user/repo.git branch-name

        return (bool) git::ls_remote('--heads', $this->fetchUrl, $branch);
    }

    public function remove(): bool
    {
        git::remote('rm', $this->name);

        return git::result() === 0;
    }

    public function setFetchUrl(?string $url = null): self
    {
        $this->fetchUrl = $url;

        return $this;
    }

    public function setHeadBranch(string $branch): self
    {
        $this->HEAD_branch = $branch;

        return $this;
    }

    public function setPushUrl(?string $url = null): self
    {
        $this->pushUrl = $url;

        return $this;
    }

    public function show()
    {
        /*
git remote show origin
* remote origin
  Fetch URL: http://amccabe@codemonkey.stars:7990/scm/stars20/master.git
  Push  URL: http://amccabe@codemonkey.stars:7990/scm/stars20/master.git
  HEAD branch: development
  Remote branches:
    CameraScanner                                                                           tracked
    KA-1-admin-sign-in                                                                      tracked
    KA-1-contact-service-package                                                            tracked
    bugfix/KA-207-kiosk-version-1.6.0                                                       tracked
    bugfix/KA-215-fix-audit-bug                                                             tracked
    bugfix/KA-219-update-unstipend-reason                                                   tracked
    development                                                                             tracked
    feature/POR-426-surfly-integration                                                      tracked
    feature/POR-432-documents-create-document-permissions-v2                                tracked
    feature/POR-438-branch-off-dev                                                          tracked
    master                                                                                  tracked
    release/portal-1.62.0                                                                   tracked
    release/portal-1.71.0                                                                   tracked
  Local branches configured for 'git pull':
    POR-539-vue2-integration                                       merges with remote POR-539-vue2-integration
    bugfix/POR-534-nstp-cant-close-production-spnt                 merges with remote bugfix/POR-534-nstp-cant-close-production-spnt
    bugfix/POR-542-asa-portal-registration-does-not                merges with remote bugfix/POR-542-asa-portal-registration-does-not
    development                                                    merges with remote development
    feature/POR-747-portal-login-text-updates                      merges with remote feature/POR-747-portal-login-text-updates
    feature/POR-752-upgrade-laravel-to-v6                          merges with remote feature/POR-752-upgrade-laravel-to-v6
    release/portal-1.71.0                                          merges with remote release/portal-1.71.0
    sprint                                                         merges with remote sprint
  Local refs configured for 'git push':
    POR-539-vue2-integration                                      pushes to POR-539-vue2-integration                                      (up to date)
    bugfix/POR-657-date-formatting-in-member-search               pushes to bugfix/POR-657-date-formatting-in-member-search               (up to date)
    bugfix/POR-659-email-field-error                              pushes to bugfix/POR-659-email-field-error                              (up to date)
    development                                                   pushes to development                                                   (local out of date)
    feature/POR-548-forgot-username-feature-dev                   pushes to feature/POR-548-forgot-username-feature-dev                   (up to date)
    feature/POR-607-automate-gdpr-checks                          pushes to feature/POR-607-automate-gdpr-checks                          (fast-forwardable)
    feature/POR-617-login-users-to-the-lms-using-a-jwt            pushes to feature/POR-617-login-users-to-the-lms-using-a-jwt            (local out of date)
    feature/POR-641-person-search-and-expired-docs-display        pushes to feature/POR-641-person-search-and-expired-docs-display        (up to date)
        */
        $output = git::remote('show', $this->name);
        $local_branches = [];
        $local_refs = [];

        while (!empty($output)) {
            $line = array_shift($output);
            
            if (Str::startsWith($line, '* remote ')) {
                $line = array_shift($output);
            }
            
            if (Str::startsWith($line, '  Fetch URL:')) {
                $this->setFetchUrl(Str::lprune($line, '  Fetch URL: '));
                $line = array_shift($output);
            }
            
            if (Str::startsWith($line, '  Push  URL:')) {
                $this->setPushUrl(Str::lprune($line, '  Push  URL: '));
                $line = array_shift($output);
            }

            //  HEAD branch: development
            
            if (Str::startsWith($line, '  HEAD branch:')) {
                $this->setHeadBranch(Str::lprune($line, '  HEAD branch: '));
                $line = array_shift($output);
            }

            if (Str::startsWith($line, '  Remote branch')) {
                while (!empty($output)) {
                    $line = array_shift($output);

                    if (Str::startsWith($line, '  Local branch')) {
                        break;
                    }

                    //    KA-1-admin-sign-in                                                                      tracked
                    list($branch, $status) = preg_split('/\s+/', trim($line), 2);

                    $this->branches[$branch] = new Branch($branch, $status === 'tracked');
                }
            }

            if (Str::startsWith($line, '  Local branch')) {
                while (!empty($output)) {
                    $line = array_shift($output);
                    $merges = null;

                    if (Str::startsWith($line, '  Local ref')) {
                        break;
                    }

                    //    development                                                    merges with remote development
                    list($branch, $merges) = preg_split('/\s+/', trim($line), 2);

                    if (Str::startsWith($merges, 'merges with remote ')) {
                        $merges = Str::lprune($merges, 'merges with remote ');
                    }
                    

                    $local_branches[$branch] = new Branch($branch);
                    $local_branches[$branch]->setMergeTo(new Branch($merges));

                    if ($merges && isset($this->branches[$branch])) {
                        $this->branches[$branch]->setMergeTo(new Branch($merges));
                    }
                }
            }

            if (Str::startsWith($line, '  Local ref')) {
                while (!empty($output)) {
                    $line = array_shift($output);
                    $pushes = null;
                    $status = null;

                    //    development                                                   pushes to development              (up to date)
                    list($branch, $pushes) = preg_split('/\s+/', trim($line), 2);

                    if (Str::startsWith($pushes, 'pushes to ')) {
                        $pushes = Str::lprune($pushes, 'pushes to ');
                    }

                    if (false !== strpos($pushes, '(')) {
                        list($pushes, $status) = preg_split('/\s+/', $pushes, 2);
                        $status = trim($status, '()');
                    }


                    $local_refs[$branch] = new Branch($branch);
                    $local_refs[$branch]->setPushTo(new Branch($pushes));

                    if ($pushes && isset($this->branches[$branch])) {
                        $this->branches[$branch]->setStatus($status);
                        $this->branches[$branch]->setPushTo(new Branch($pushes));
                    }
                }
            }
        }

        return array_merge($local_branches, $local_refs);
    }

    public static function all(): array
    {
        $Remotes = [];
        $output = git::remote('-v');

        foreach ($output as $remote) {
            list($name, $url, $type) = preg_split('/\s+/', $remote, 3);
            $Remote = new Remote($name);

            if (!isset($Remotes[$name])) {
                $Remotes[$name] = $Remote;
            }

            if ($type === '(fetch)') $Remotes[$name]->setFetchUrl($url);
            if ($type === '(push)') $Remotes[$name]->setPushUrl($url);
        }

        return $Remotes;
    }
}