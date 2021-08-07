<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Collection;
use PhpCli\Exceptions\GitException;
use PhpCli\Facades\Log;
use PhpCli\Filesystem\Directory;
use PhpCli\Observers\Observable;
use PhpCli\Observers\Subject;
use PhpCli\Output;
use PhpCli\Reporting\Logger;
use PhpCli\Reporting\Drivers\BufferLogger;
use PhpCli\Reporting\Drivers\NullLogger;
use PhpCli\Str;
use PhpCli\Validation\UrlRule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Repository implements Subject {

    use Observable {
        Observable::__construct as private intialize;
    }

    private ?Branch $Branch;

    private string $directory;

    private Commit $HEAD;

    private Stage $Stage;

    private Logger $Logger;

    private Output $output;

    private Collection $Remotes;

    private array $untracked_files;

    private string $upstream_branch;

    public const STR_CHANGES_TO_BE_COMMITTED = 'Changes to be committed:';

    public const STR_CHANGES_NOT_STAGED = 'Changes not staged for commit:';

    public const STR_UNMERGED_PATHS = 'Unmerged paths:';

    public const STR_UNTRACKED_FILES = 'Untracked files:';

    /**
     * Workflows
     * 
     * checkout a new remote branch
     * git fetch --prune            $this->fetch(true)
     * git checkout <branchname>    $this->checkout('<branchname>')
     *                              -OR-
     *                              $this->fetch(true)->checkout('<branchname>')
     * 
     * create and push a new tag
     * git tag <tag>                $this->tag('<tag>')
     * git push <remote> <tag>      $this->pushTag('<tag>')
     *                              -OR-
     *                              $this->tag('<tag>')->pushTag('<tag>')
     * 
     * add, commit, and push changes
     * git add <ref>                $this->add('<ref>')
     * git commit -m <message>      $this->commit('<message>')
     * git push                     $this->push()
     *                              -OR-
     *                              $this->add('<ref>')->commit('<message>')->push()
     * 
     * @todo
     * 
     * 
     */

    public function __construct(string $directory = null)
    {
        if (null === $directory) {
            $directory = getcwd();
        }
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        git::cd($this->directory);
        $this->Stage = new Stage($this);
        $this->output = new Output();
        $this->intialize();
    }

    /**
     * This command updates the index using the current content found in the
     * working tree, to prepare the content staged for the next commit. 
     *
     * @param [type] $file
     * @return self
     */
    public function add($file): self
    {
        if (!$this->Stage->add($file)) {
            throw new \Exception('Error adding file to the index.');
        }

        return $this;
    }

    /**
     * Add a remote repository.
     *
     * @param string $name
     * @param string $url
     * @return bool
     */
    public function addRemote(string $name, string $url, bool $fetch = false, bool $tags = false): bool
    {
        $Remote = new Remote($name, $url);

        $this->_log($Remote->add($fetch, $tags));

        if (git::result() === 0) {
            $this->Remotes->set($name, $Remote);

            return true;
        }

        return false;
    }

    /**
     * Get the current branch.
     *
     * @return Branch
     */
    public function branch(string $new = null, string $from = null): Branch
    {
        $this->validateInitialized();

        if (is_null($new)) {
            $output = git::rev_parse('--abbrev-ref', 'HEAD');

            if ($output[0] === '* (no branch)') {
                $this->Branch = null;
            } else {
                $this->setBranch($output[0]);
            }

            return $this->Branch;

        } else {
            if (is_null($from)) {
                git::branch($new);
            } else {
                git::branch($new, $from);
            }
        }

        return $this->getBranch($new);        
    }

    /**
     * Get a list of branches.
     * 
     * @return Collection
     */
    public function branches(): Collection
    {
        // $branches = git::branch('--a');
        $Branches = new Collection();

        // foreach ($branches as $branch) {
        //     $isLocal = false;
        //     if ($isCheckedOut = Str::startsWith($branch, '*')) {
        //         $branch = Str::replace($branch, '*', '');
        //         $isLocal = true;
        //     }
            
        //     $branch = trim($branch);
            
        //     if ($isRemote = Str::startsWith($branch, 'remotes')) {
        //         [, $remoteName, $branch] = explode('/', $branch);

        //         $Branch = new Branch($branch, $isLocal && $isRemote, $isCheckedOut);

                // if ($Remote = $this->remote($remoteName)) {
                //     $Branch->setRemote($Remote);
                // }
        //     } else {
        //         $isLocal = true;
        //         $Branch = new Branch($branch, $isLocal && $isRemote, $isCheckedOut);
        //     }

        //     $Branches->push($Branch);
        // }

        /*
> git branch -vv
  development 220b2bf Fix database driver rename.
* main        9dacb73 [origin/main: ahead 1] Broad refactors and bug fixes.
        */

        // Iterate all local branches and their tracking data
        foreach (git::branch('-vv') as $branchInfo) {
            $isLocal = true;
            
            if ($isCheckedOut = Str::startsWith($branchInfo, '*')) {
                $branchInfo = Str::replace($branchInfo, '*', '');
            }

            [$name, $info] = preg_split('/\s+/', trim($branchInfo), 2);
            [$sha, $info] = preg_split('/\s+/', trim($info), 2);

            // append '[]' to the info to avoid getting commit message data if no tracking info exists
            $trackingInfo = trim(Str::capture($info.' []', '[', ']'));

    // print_r(compact('name', 'sha', 'trackingInfo'));

            $Branch = new Branch($name, !empty($trackingInfo), $isCheckedOut);

            if ($trackingInfo) {
                $status = null;

                if (preg_match('/\s/', $trackingInfo) === 1) {
                    [$remote, $status] = preg_split('/\s+/', trim($trackingInfo), 2);
                } else {
                    $remote = $trackingInfo;
                }
                

                // 0 => "origin/main:" 
                if ($remote) {
                    [$remote, $remoteBranch] = explode('/', $remote);
                    $remoteBranch = rtrim($remoteBranch, ':');
                    
                    if ($RemoteBranch = $this->getBranch($remote . '/' . $remoteBranch)) {
                        $Branch->setMergeTo($RemoteBranch);
                        $Branch->setPushTo($RemoteBranch);
                    }

                    if ($Remote = $this->remote($remote)) {
                        $Branch->setRemote($Remote);
                    }
                }

                // 1 => "ahead 1"
                if ($status) {
                    if (Str::contains($status, ',')) {
                        foreach ($aheadBehind = explode(',', $status) as $status) {
                            if (Str::startsWith($status, 'ahead')) {
                                if ($ahead = intval(Str::after($status, 'ahead '))) {
                                    $Branch->setAhead($ahead);
                                }
                            } elseif (Str::startsWith($status, 'behind')) {
                                if ($behind = intval(Str::after($status, 'behind '))) {
                                    $Branch->setBehind($behind);
                                }
                            }
                        }

                    } elseif (Str::startsWith($status, 'ahead')) {
                        if ($ahead = intval(Str::after($status, 'ahead '))) {
                            $Branch->setAhead($ahead);
                        }
                    } elseif (Str::startsWith($status, 'behind')) {
                        if ($behind = intval(Str::after($status, 'behind '))) {
                            $Branch->setBehind($behind);
                        }
                    }
                }
            }
            $Branches->push($Branch);
        }
        
        // Iterate all remote branches
        $HEADBranch = [
            'remote' => '',
            'branch' => ''
        ];
        foreach (git::branch('-r') as $branch) {
            //setIsHead(
            /*
  origin/HEAD -> origin/main
  origin/main
            */

            if (Str::contains($branch, '/HEAD ->')) {
                [$remoteHEAD, $branch] = explode(' -> ', $branch);
                [$remote, ] = explode('/', $remoteHEAD);
                $HEADBranch['remote'] = trim($remote);
                $HEADBranch['branch'] = trim($branch);

                continue;
            }

            [$remoteName, $branch] = explode('/', $branch);
            $remoteName = trim($remoteName);

            $contains = $Branches->first(function (Branch $Branch) use ($remoteName, $branch) {
                $RemoteBranch = $Branch->mergesTo() ?? $Branch->pushesTo();
                if ($RemoteBranch) {
                    return $RemoteBranch->name() === $remoteName . '/' . $branch;
                }
                return false;
            });

            if ($contains) {
                if ($contains->name() === $HEADBranch['branch']) {
                    $contains->setIsHead();
                }
                continue;
            }


            $Branch = new Branch($remoteName . '/' . $branch, !empty($trackingInfo), false);

            if ($Branch->name() === $HEADBranch['branch']) {
                $Branch->setIsHead();
            }

            if ($Remote = $this->remote($remoteName)) {
                $Branch->setRemote($Remote);
            }

            $Branches->push($Branch);
        }

        return $Branches;
    }

    /**
     * To prepare for working on <branch>, switch to it by updating the index 
     * and the files in the working tree, and by pointing HEAD at the branch.
     * Local modifications to the files in the working tree are kept, so that 
     * they can be committed to the <branch>.
     * If Remote is provided, create/reset branch to the point referenced by origin/branch.
     *
     * @param [type] $branch
     * @param boolean $stash
     * @return self
     */
    public function checkout($branch, $Remote = null, bool $new = false, bool $stash = false): self
    {
        $remote = null;

        if (is_string($branch)) {
            $Branch = $this->getBranch($branch);
        } elseif ($branch instanceof Branch) {
            $Branch = $branch;
            $branch = $Branch->name();
        } else {
            throw new \InvalidArgumentException();
        }
        
        if ($Remote) {
            // special case to create new branch from detached HEAD
            if (is_string($Remote) && $Remote === 'HEAD') {
                $remote = 'HEAD';

            // otherwise get the remote branch name
            } else {
                if (is_string($Remote)) {
                    $Remote = $this->remote($Remote);
                }

                $remote = $Remote->name().'/'.$branch;
            }
        }

        // has unstaged or staged changes?
        // @todo

        
        // git checkout -b branch origin/branch

        $args = [];

        if ($new) {
            $args[] = '-b';
        }

        $args[] = $branch;
        $args[] = $remote;

        $this->_log(git::checkout(...$args));

        return $this;
    }

    /**
     * Clone a local or remote repository
     * 
     * git clone https://repos.org/my-repo
     *  -> `cwd`/my-repo/.git
     * 
     * git clone https://repos.org/my-repo repo-copy
     *  -> `cwd`/repo-copy/.git
     * 
     * git clone /Users/me/repos/my-repo
     *  -> `cwd`/my-repo/.git
     * 
     * git clone /Users/me/repos/my-repo repo-copy
     *  -> `cwd`/repo-copy/.git
     * 
     * @param string $source
     * @param string $directory
     * @return Repository
     */
    public function clone(string $repository = '.', string $directory = null): Repository
    {
        [$repository, $directory, $destination] = $this->getValidatedCloneParameters($repository, $directory);

        $args = [$repository, $directory, '--verbose'];

        $this->_log(git::clone(...$args));

        $Repository = new self($destination);

        return $Repository;
    }

    /**
     * @param string $source
     * @param string $directory
     * @return array
     */
    public function getValidatedCloneParameters(string $repository = '.', string $directory = null): array
    {
        $UrlRule = new UrlRule();
        $destination = $this->directory;

        // Validate the repository
        if ($repository === '.' || $repository === './') {
            $repository = $this->directory;
        } elseif (!$UrlRule->passes('', $repository)) {
            $Dir = new Directory($repository);

            if (!$Dir->exists()) {
                throw new GitException(sprintf('Error: source directory "%s" not found.', $repository));
            }
        }

        // Validate the destination
        if ($directory) {
            $directory = ltrim(str_replace('./', '', $directory), '.');
            $Dir = new Directory($directory);

            if ($Dir->exists() && !$Dir->empty()) {
                throw new GitException(sprintf('Error: destination path \'%s\' already exists and is not an empty directory.', $directory));
            }

            $destination = $this->path($directory);
        }

        if ($repository === $destination) {
            $directory = basename($destination) . '_copy';
            $destination = $this->path($directory);
        }
        
        if (is_null($directory) && $UrlRule->passes('', $repository)) {
            $destination = $this->path(basename(Str::before($repository, '.git')));
        }

        return [$repository, $directory, $destination];
    }


    /**
     * Record staged changes to the repository.
     *
     * @param string $message
     * @return self
     */
    public function commit(?string $message, ?string $path = null, bool $amend = false): self
    {
        // require a commit message if not an amend
        if (!$amend && empty($message)) {
            throw new \InvalidArgumentException('Commit message required.');
        }

        // check if there is anything staged on the index
        if (!$amend && is_null($path) && empty($this->Stage->getChanges())) {
            throw new GitException('No changes staged.');
        }

        $args = [];

        // quote the message string
        if (!is_null($message)) {
            $args[] = '-m';
            $message = trim($message, '"');
            $message = str_replace('"', '\"', $message);
            $args[] = '"'.$message.'"';
        }

        if ($amend) {
            $args[] = '--amend';
            if (is_null($message)) {
                $args[] = '--no-edit';
            }
        }

        if ($path) {
            $args[] = $path;
        }

        $this->_log(git::commit(...$args));


        // print_r($output);
/*
Array
(
    [0] => [master a1d34e6] Add diff support to File class.
    [1] =>  1 file changed, 156 insertions(+), 15 deletions(-)
)

git commit -m "C"
Array
(
    [0] => [master (root-commit) e5cb21d] C
    [1] =>  1 file changed, 1 insertion(+)
    [2] =>  create mode 100644 myfile
)

git commit -m "Change name of new role"
Array
(
    [0] => git hook .git/hooks/commit-msg
    [1] => --------------------------------
    [2] => Branch:  POR-760-create-new-role-for-cs_access_search
    [3] => [bugfix/POR-760-create-new-role-for-cs_access_search 66e28d1c85] POR-760 Change name of new role
    [4] =>  3 files changed, 4 insertions(+), 4 deletions(-)
)
*/

        // git commit returns 1 for nested (submodule) git repositories
        if (git::result() > 1) {
            throw new \Exception('Error creating new commit.');
        }
        
        $this->head();

        return $this;
    }

    /**
     * git config getter/setter.
     *
     * @param string $key
     * @param mixed $value
     * @param boolean $global
     * @return mixed
     */
    public function config(string $key, $value = null, bool $global = false)
    {
        $args = [];

        if ($global) {
            $args[] = '--global';
        }

        $args = [$key];

        if (!is_null($value)) {
            if (!is_string($value)) {
                $value = var_export($value, true);
            }

            if (is_string($value) && Str::contains($value, ' ') && !Str::isQuoted($value)) {
                $value = '"'.$value.'"';
            }

            $args[] = $value;
        }

        $output = git::config(...$args);

        return $output[0] ?? null;
    }

    public function delete(): bool
    {
        $this->validateInitialized();

        $dir = $this->path('.git');
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return rmdir($dir);
    }

    /**
     * Delete a branch. If a Remote instance is provided the branch will
     * also be deleted on the remote repository.
     *
     * @param Branch $Branch
     * @param Remote|null $Remote
     * @return bool
     */
    public function deleteBranch(Branch $Branch, ?Remote $Remote = null, bool $force = false): bool
    {
        $this->_log($Branch->delete($force));

        if ($Remote) {
            git::push($Remote->name(), ':'.$Branch->name());

            return git::result() === 0;
        }
        return true;
    }

    /**
     * Output the diff to stdout.
     *
     * @param string $path
     * @return string
     */
    public function diff(string $path = null, bool $staged = false): self
    {
        $diff = $this->renderDiff($path, $staged);

        $this->output->line($diff);

        return $this;
    }

    /**
     * Discard local changes (unstages if staged).
     */
    public function discard(string $path = null)
    {
        // $output = [];
        $StagedFiles = new Collection($this->getDiff($path, true));

        if (!$StagedFiles->empty()) {
            $StagedFiles->each(function (File $File)/* use (&$output)*/ {
                // $output = array_merge($output, git::reset('--', $File->getPath()));
                $this->Stage->removeFile($File);
            });
        }

        $Files = new Collection($this->getDiff($path, false));
        $ModifiedFiles = $Files->filter(function (File $File) {
            return $File->isModified();
        });

        if (!$ModifiedFiles->empty()) {
            // if (version_compare(git::version(), '2.25', '>')) {
            //     $ModifiedFiles->each(function (File $File) use (&$output) {
            //         $output = array_merge($output, ...git::restore($File->getPath()));
            //     });
            // } else {
            //     $ModifiedFiles->each(function (File $File) use (&$output) {
            //         $output = array_merge($output, ...git::checkout('--', $File->getPath()));
            //     });
            // }
            $ModifiedFiles->each(function (File $File) {
                $this->Stage->discardFile($File);
            });
        }

        // $this->_log($output);

        return $this;
    }

    /**
     * Check if a git repository exists in the cofigured path.
     *
     * @return boolean
     */
    public function exists(): bool
    {
        return $this->isInitialized();
    }

    /**
     * Fetch refs from the remote repository.
     *
     * @param boolean $prune
     * @param Remote $Remote
     * @return self
     */
    public function fetch(bool $prune = false, Remote $Remote = null): self
    {
        $Remote ??= $this->remote();

        if (is_null($Remote)) {
            throw new GitException('No configured fetch source.');
        }

        $this->_log($Remote->fetch($prune));

        if (git::result() > 0) {
            throw new GitException(sprintf('Error fetching from remote "%s"', $Remote->name()));
        }

        return $this;
    }

    public function getBranch(string $name = null): Branch
    {
        if ($name) {
            return new Branch($name);
        }

        return $this->branch();
    }

    public function getCommit(string $hash): Commit
    {
        return new Commit($hash);
    }

    /**
     * Get an array of Files each with their discrete diff.
     *
     * @param string $path
     * @param bool $staged
     * @return array
     */
    public function getDiff(string $path = null, bool $staged = false): array
    {
        $args = [];

        if ($staged) {
            $args[] = '--cached';
            $args[] = '--';
        }

        $args[] = $path;

        $lines = git::diff(...$args);
        $diffs = Diff::parseFiles($lines);

        return $diffs;
    }

    /**
     * Get the local HEAD branch
     * 
     * @return string
     */
    public function getHEADBranch(): Branch
    {
        $branches = git::branch('-l');
        if (empty($branches)) {
            throw new GitException(sprintf('No branches found in respository at \'%s\'', $this->directory));
        }

        $name = ltrim(trim($branches[0]), '*');
        while ($branch = array_shift($branches)) {
            $branch = ltrim(trim($branch), '*');
            if (in_array($branch, ['master', 'main', 'trunk'])) {
                return $this->getBranch($branch);
            }
        }

        return $this->getBranch($name);
    }

    /**
     * Get the file contents of .git/description
     *
     * @return string
     */
    public function getName(): string
    {
        $this->validateInitialized();

        $name = trim(file_get_contents($this->path('.git/description')));
        if (false !== strpos($name, 'Unnamed repository')) {
            $name = 'Unnamed repository';
        }

        return $name;
    }

    public function head(): ?Commit
    {
        $output = git::rev_parse('HEAD');

        $this->setHead($output[0]);

        if ($this->HEAD) {
            return $this->HEAD->load();
        }

        return null;
    }

    /**
     * Initialize a git repository in the configured path. If the path directory does not exist, it will be created.
     *
     * @return boolean
     */
    public function init(): bool
    {
        git::init($this->directory);

        return git::result() === 0;
    }

    public function isHeadDetached(): bool
    {
        $this->validateInitialized();

        $output = git::rev_parse('--abbrev-ref', 'HEAD');

        return $output[0] === '* (no branch)';
    }

    /**
     * Check if a git repository is initialized in the configured path.
     *
     * @return boolean
     */
    public function isInitialized(): bool
    {
        return file_exists($this->path('.git'));
    }


    /**
     * Get commit history for the checked out branch. Optionally provide start
     * and stop commit hashes.
     *
     * @param string $start
     * @param string|null $stop
     * @return void
     */
    public function log(string $start = 'HEAD', ?string $stop = null)
    {
        $commits = [];

        if ($stop) {
            $start = $stop.'~1..'.$start;
        }

        $output = git::log($start);

        foreach ($output as $line) {
            if (Str::startsWith($line, 'commit ')) {
                $commits[] = Commit::get(Str::lprune($line, 'commit '));
            }
        }

        return $commits;
    }

    public function merge(...$branches)
    {
        $args = array_map(function ($branch) {
            if ($branch instanceof Branch) {
                return $branch->name();
            }
            if (is_string($branch)) {
                return $branch;
            }
            throw new \InvalidArgumentException();
        }, $branches);

        // $args[] = '--autostash';

        $this->_log(git::merge(...$args));

        // print_r($output);
        /*
git merge development
Array
(
    [0] => Removing portal/database/migrations/2021_04_20_123302_POR-765.php
    [1] => Removing database/POR-765.sql
    [2] => git hook .git/hooks/commit-msg
    [3] => --------------------------------
    [4] => Merge made by the 'recursive' strategy.
    [5] =>  database/POR-765.sql                               | 77 ----------------------
    [6] =>  portal/VERSION                                     |  2 +-
    [7] =>  .../Http/Controllers/Roster/IattfController.php    | 25 ++++++-
    [8] =>  .../app/Http/Controllers/Roster/TcrController.php  | 25 ++++++-
    [9] =>  portal/app_version.php                             |  2 +-
    [10] =>  .../migrations/2021_04_20_123302_POR-765.php       | 33 ----------
    [11] =>  .../views/roster/only-single-search.blade.php      | 15 +++++
    [12] =>  portal/resources/views/roster/search.blade.php     | 38 ++++++-----
    [13] =>  portal/routes/web.php                              |  4 ++
    [14] =>  9 files changed, 90 insertions(+), 131 deletions(-)
    [15] =>  delete mode 100644 database/POR-765.sql
    [16] =>  delete mode 100644 portal/database/migrations/2021_04_20_123302_POR-765.php
    [17] =>  create mode 100644 portal/resources/views/roster/only-single-search.blade.php
)
        */

        return $this;
    }

    public function mergeAbort(): self
    {
        $output = git::merge('--abort');

        $this->_log($output);

        print "\n".__METHOD__.':'.__LINE__;
        print_r($output);

        return $this;
    }

    /**
     * Get the full path for the provided relative path.
     *
     * @param string $directory
     * @return string
     */
    public function path($directory = ''): string
    {
        if (Str::startsWith($directory, DIRECTORY_SEPARATOR)) {
            return $directory;
        }

        $path = $this->directory;

        if ($directory = trim(trim($directory), DIRECTORY_SEPARATOR)) {
            $path .= DIRECTORY_SEPARATOR . $directory;
        }

        return $path;
    }

    public function publishBranch(?Remote $Remote = null, $branch): bool
    {
        $Branch = $this->getBranch($branch);
        $Remote ??= $this->remote();

        if (is_null($Remote)) {
            throw new \Exception('No configured push destination.');
        }

        $this->_log(git::push('-u', $Remote->name(), $Branch->name()));

        return git::result() === 0;
    }

    /**
     * Update this repository with remote.
     *
     * @param Remote|null $Remote
     * @param string $remoteBranch
     * @return self
     */
    public function pull(?Remote $Remote = null, string $remoteBranch = null): self
    {
        $args = [];

        // has unstaged or staged changes?
        // @todo

        print "\n".__METHOD__.':'.__LINE__;
        print ($this->Stage->hasChanges() ? "has changes" : "has no changes")."\n";
        print_r($this->getStatus());

        // $args = ['--rebase'];
        $Remote ??= $this->remote();

        if (is_null($Remote)) {
            throw new \Exception('No configured pull source (remote).');
        }

        if ($remoteBranch) {
            $args[] = $Remote->name();
            $args[] = $remoteBranch;
        }

        $this->_log(git::pull(...$args));

        /*

        pull()
        Array
        (
            [0] => Already up to date.
            [1] => Current branch bugfix/POR-760-create-new-role-for-cs_access_search is up to date.
        )

        pull(null, 'development') // while on another branch (bugfix/POR-789-remove-asa-multi-search-option)
        Array
        (
            [0] => From http://codemonkey.stars:7990/scm/stars20/master
            [1] =>  * branch                  development -> FETCH_HEAD
            [2] => Updating 94adc82f5f..908aa20027
            [3] => Fast-forward
            [4] =>  database/POR-765.sql                               | 77 ----------------------
            [5] =>  portal/VERSION                                     |  2 +-
            [6] =>  portal/app_version.php                             |  2 +-
            [7] =>  .../migrations/2021_04_20_123302_POR-765.php       | 33 ----------
            [8] =>  4 files changed, 2 insertions(+), 112 deletions(-)
            [9] =>  delete mode 100644 database/POR-765.sql
            [10] =>  delete mode 100644 portal/database/migrations/2021_04_20_123302_POR-765.php
            [11] => Current branch bugfix/POR-789-remove-asa-multi-search-option is up to date.
        )


        pull() - expecting merge conflitcs
        git pull --rebase
        Array
        (
            [0] => error: cannot pull with rebase: You have unstaged changes.
            [1] => error: please commit or stash them.
        )


        git pull
        Array
        (
            [0] => error: Your local changes to the following files would be overwritten by merge:
            [1] => 	portal/routes/web.php
            [2] => Please commit your changes or stash them before you merge.
            [3] => Aborting
            [4] => Updating c741b879ab..908aa20027
        )

        ...?

Exception with message 'Error:  Your local changes to the following files would be overwritten by merge:
	portal/routes/web.php
Please commit your changes or stash them before you merge.
Aborting
Updating c741b879ab..908aa20027'

...discarded changes to web.php

 git pull
Array
(
    [0] => Updating c741b879ab..908aa20027
    [1] => Fast-forward
    [2] =>  database/POR-765.sql                               | 77 ----------------------
    [3] =>  portal/VERSION                                     |  2 +-
    [4] =>  .../Http/Controllers/Roster/IattfController.php    | 25 ++++++-
    [5] =>  .../app/Http/Controllers/Roster/TcrController.php  | 25 ++++++-
    [6] =>  portal/app_version.php                             |  2 +-
    [7] =>  .../migrations/2021_04_20_123302_POR-765.php       | 33 ----------
    [8] =>  .../views/roster/only-single-search.blade.php      | 15 +++++
    [9] =>  portal/resources/views/roster/search.blade.php     | 38 ++++++-----
    [10] =>  portal/routes/web.php                              |  4 ++
    [11] =>  9 files changed, 90 insertions(+), 131 deletions(-)
    [12] =>  delete mode 100644 database/POR-765.sql
    [13] =>  delete mode 100644 portal/database/migrations/2021_04_20_123302_POR-765.php
    [14] =>  create mode 100644 portal/resources/views/roster/only-single-search.blade.php
)


        */

        // capture response status (ie. "new branch")
        // if (isset($output[0])) {
        //     return $output[0];
        // }

        if (git::result()) {
            throw new \Exception('Error pulling from remote repository.');
        }

        return $this;
    }

    /**
     * Update a remote repository.
     * The --force option must be used to push an amended commit.
     *
     * @param Remote|null $Remote
     * @param string|null $branch
     * @param [type] $tags
     * @return self
     */
    public function push(?Remote $Remote = null, string $remoteBranch = null, $tags = null, bool $force = false): self
    {
        $args = [];
        $branches = $this->branch()->name();
        $Remote ??= $this->remote();
        $stashed = false;

        // Pull before push? check custom config
        if ($this->config('core.prepushpull') === 'true') {
            
            // Has file changes?
            // @todo

            $this->pull();

            if ($stashed) {
                git::stash('apply');
            }
        }


        // $args[] = '--dry-run';

        if (is_null($Remote)) {
            throw new \Exception('No configured push destination (remote).');
        }

        $args[] = $Remote->name();

        if (!is_null($tags)) {
            if ($tags === true) {
                $args[] = '--tags';
            } elseif (is_string($tags)) {
                $args[] = $tags;
            }
        }

        if ($force) {
            $args[] = '--force';
        }

        if ($remoteBranch) {
            $args[] = $Remote->name();

            if ($remoteBranch === true) {
                $args[] = $branches;
                $args[] = 'HEAD';
            } else {
                $branches .= ':'.$remoteBranch;
                $args[] = $branches;
            }
        } else {
            $args[] = $branches;
        }

        $this->_log(git::push(...$args));

        /*

        push branch that does not exist on remote

        Array
        (
            [0] => To http://codemonkey.stars:7990/scm/stars20/master.git
            [1] =>  * [new branch]            feature/POR-765-online-roster-multi-search-via-csv -> feature/POR-765-online-roster-multi-search-via-csv
        )


        push branch that exists on remote

        Array
        (
            [0] => To http://codemonkey.stars:7990/scm/stars20/master.git
            [1] =>    c741b879ab..7b29406d00  feature/POR-784-prompt-to-download-cs-access-when -> feature/POR-784-prompt-to-download-cs-access-when
        )
        */

        // capture response status (ie. "new branch")
        // if (isset($output[1]) && Str::contains($output[1], '[')) {
        //     return Str::capture($output[1], '[', ']');
        // }

        if (git::result()) {
            throw new \Exception('Error pushing to remote repository.');
        }

        return $this;
    }

    /**
     * Publish the specified tag to the remote repository.
     *
     * @param string $tag
     * @return self
     */
    public function pushTag(string $tag): self
    {
        return $this->push(null, null, $tag);
    }

    /**
     * Get the remote reference with the provided name.
     *
     * @param string|null $name
     * @return Remote|null
     */
    public function remote(?string $name = null, bool $refreshRemotes = false): ?Remote
    {
        $this->validateInitialized();

        if (is_null($name)) {
            $output = git::remote('show');
            if (isset($output[0])) {
                $name = $output[0];
            }
        }

        if ($name) {
            if (!isset($this->Remotes) || $refreshRemotes) {
                $this->remotes();
            }

            if (isset($this->Remotes)) {
                return $this->Remotes->get($name);
            }
        }
        
        return null;
    }

    public function remotes(): Collection
    {
        $this->Remotes = new Collection(Remote::all());

        return $this->Remotes;
    }

    /**
     * Unstage a file from the index.
     * 
     * @param [type] $file
     * @return self
     */
    public function remove($file): self
    {
        if (!$this->Stage->remove($file)) {
            throw new \Exception('Error removing file from the index.');
        }

        return $this;
    }

    /**
     * Remove a remote repository.
     *
     * @param string $name
     * @return bool
     */
    public function removeRemote(string $name): bool
    {
        $this->validateHasRemote($name);

        $Remote = new Remote($name);

        $this->_log($Remote->remove());

        if (git::result() === 0) {
            $this->Remotes->pull($name);

            return true;
        }

        return false;
    }

    /**
     * Rename a remote repository.
     *
     * @param string $oldName
     * @param string $newName
     * @return boolean
     */
    public function renameRemote(string $oldName, string $newName): bool
    {
        $this->validateHasRemote($oldName);

        $this->_log(git::remote('rename', $oldName, $newName));

        return git::result() === 0;
    }

    public function renderCommitGraph()
    {
        $graph = git::log(
            '--graph',
            '--abbrev-commit',
            '--decorate',
            '--format=format:\'%C(bold blue)%h%C(reset) - %C(bold green)(%ar)%C(reset) %C(white)%s%C(reset) %C(dim white)- %an%C(reset)%C(bold yellow)%d%C(reset)\'',
            '--all'
        );

        return implode("\n", $graph);
    }

    /**
     * Render the diff to a string.
     *
     * @param string $path
     * @return string
     */
    public function renderDiff(string $path = null, bool $staged = false): string
    {
        $diffs = $this->getDiff($path, $staged);

        if (empty($diffs)) {
            return '';
        }

        foreach ($diffs as $File) {
            $diffString = $File->diff() . '';
            $lines = explode("\n", $diffString);

            foreach ($lines as $lkey => $line) {
                if (!empty($line)) {
                    if ($line[0] === '-' && (!isset($line[1]) || $line[1] !== '-')) {
                        $lines[$lkey] = Output::color($line, 'red');
                    } elseif ($line[0] === '+' && (!isset($line[1]) || $line[1] !== '+')) {
                        $lines[$lkey] = Output::color($line, 'green');
                    } elseif ($line[0] === '-' && isset($line[1]) && $line[1] === '-') {
                        $lines[$lkey] = Output::bold($line);
                    } elseif ($line[0] === '+' && isset($line[1]) && $line[1] === '+') {
                        $lines[$lkey] = Output::bold($line);
                    } elseif (Str::startsWith($line, '@@ ')) {
                        if ($sectionHeader = Str::capture($line, '@@', '@@')) {
                            $sectionHeader = '@@' . $sectionHeader . '@@';
                            $lines[$lkey] = Str::replace($line, $sectionHeader, Output::color($sectionHeader, 'light_purple'));
                        }
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get the status as a string.
     *
     * @return string
     */
    public function renderStatus(string $path = null): string
    {
        $output = '';
        $status = $this->getStatus($path);

        foreach ($status as $group => $Files) {
            $output .= "\n" . $group . "\n";
            $color = null;

            switch ($group) {
                case self::STR_CHANGES_TO_BE_COMMITTED:
                    $color = 'green';
                    break;
                case self::STR_CHANGES_NOT_STAGED:
                case self::STR_UNMERGED_PATHS:
                case self::STR_UNTRACKED_FILES:
                    $color = 'red';
                    break;
            }

            $filesData = [];
            $longestStatus = 0;

            foreach ($Files as $File) {
                $status = $File->status();
                $len = Str::length($status);
                $filesData[$File->getPath()] = $status;
                if ($len > $longestStatus) {
                    $longestStatus = $len;
                }
            }

            foreach ($filesData as $path => $status) {
                if ($status) {
                    $status = Str::pad($status.': ', $longestStatus + 5).$path;
                } else {
                    $status = $path;
                }
                
                if ($color) {
                    $status = Output::color($status, $color);
                }
                $output .= "    " . $status . "\n";
            }
        }

        return $output;
    }

    /**
     * This command updates the index using the current content found in the
     * working tree, to prepare the content staged for the next commit. 
     *
     * @param [type] $file
     * @return self
     */
    public function stage($file): self
    {
        if (!$this->Stage->add($file)) {
            throw new \Exception('Error staging file.');
        }

        return $this;
    }

    /**
     * Output the status.
     *
     * @return self
     */
    public function status(string $path = null): self
    {
        $this->output->line($this->renderStatus($path));

        return $this;
    }

    /**
     * Set the file contents of .git/description
     *
     * @param string $name
     * @return boolean|null
     */
    public function setName(string $name): ?bool
    {
        $this->validateInitialized();

        return (bool) file_put_contents($this->path('.git/description'), $name);
    }

    /**
     * Unstage a file from the index.
     * 
     * @param [type] $file
     * @return self
     */
    public function unstage($file): self
    {
        if (!$this->Stage->remove($file)) {
            throw new \Exception('Error unstaging file.');
        }

        return $this;
    }

    /**
     * Get the status of the working tree.
     *
     * @return array
     */
    public function getStatus(string $path = null)
    {
        $this->validateInitialized();

        $headers = [];
        $changes = [];
        $output = git::status(git::porcelain(), '--branch', $path);

        foreach ($output as $line) {
            if ($line[0] === '#') {
                $headers[] = ltrim(trim($line), '#');
            } elseif (strlen($line)) {
                $changes[] = $line;
            }
        }

        // parse headers
        if (!empty($headers)) {
            foreach ($headers as $header) {
                // branch.oid <commit> | (initial)        Current commit.
                if (false !== strpos($header, 'branch.oid') && false === strpos($header, '(initial)')) {
                    if ($commit = str_replace('branch.oid ', '', $header)) {
                        $this->setHead($commit);
                    }
                }
                // branch.head <branch> | (detached)      Current branch.
                if (false !== strpos($header, 'branch.head')) {
                    if ($branch = str_replace('branch.head ', '', $header)) {
                        $this->setBranch($branch);
                    }
                }

                // branch.upstream <upstream_branch>      If upstream is set.
                if (false !== strpos($header, 'branch.upstream')) {
                    if ($branch = str_replace('branch.upstream ', '', $header)) {
                        $this->setUpstreamBranch($branch);
                    }
                }

                // branch.ab +<ahead> -<behind>           If upstream is set and the commit is present.
                if (false !== strpos($header, 'branch.ab')) {
                    $header = str_replace('branch.ab ', '', $header);
                    $ahead = intval(Str::capture($header, '+', ' '));
                    $behind = intval(Str::capture($header, '-'));
                    $this->HEAD->setAheadBehind($ahead, $behind);
                }
            }
        }

        // parse changes
        $Files = array_map(function ($line) {
            $info = File::parseStatus($line);
            $File = new File($info['path'], $info['worktree_status']);

            if (isset($info['index_status'])) {
                $File->setIndexStatus($info['index_status']);
            }
            if (isset($info['HEAD_file_mode'])) {
                $File->setFileMode($info['HEAD_file_mode'], 'HEAD');
            }
            if (isset($info['HEAD_object_name'])) {
                $File->setObjectName($info['HEAD_object_name'], 'HEAD');
            }
            if (isset($info['index_file_mode'])) {
                $File->setFileMode($info['index_file_mode'], 'index');
            }
            if (isset($info['index_object_name'])) {
                $File->setObjectName($info['index_object_name'], 'index');
            }
            if (isset($info['worktree_file_mode'])) {
                $File->setFileMode($info['worktree_file_mode'], 'worktree');
            }
            if (isset($info['original_path'])) {
                $File->setOriginalPath($info['original_path']);
            }

            return $File;
        }, $changes);

        if (is_null($path)) {
            $status = [
                self::STR_CHANGES_TO_BE_COMMITTED => [],
                self::STR_CHANGES_NOT_STAGED => [],
                self::STR_UNMERGED_PATHS => [],
                self::STR_UNTRACKED_FILES => []
            ];

            foreach ($Files as $File) {
                if ($fileStatus = static::getFileStatus($File)) {
                    $status[$fileStatus][] = $File;
                    continue;
                }
            }

            return $status;
        }

        return $Files;
    }

    /**
     * Create a tag at HEAD
     *
     * @param string $tag
     * @return self
     */
    public function tag(string $tag): self
    {
        $this->_log(git::tag($tag));
        
        if (git::result()) {
            throw new \Exception(sprintf('Error creating tag "%s"', $tag));
        }

        return $this;
    }

    /**
     * Return the local git binary version.
     *
     * @return string
     */
    public function version(): string
    {
        $this->validateInitialized();
        return git::version();
    }

    /**
     * Set the current (checked-out) branch
     *
     * @param string $branch
     * @return self
     */
    private function setBranch(string $branch): self
    {
        if ($branch instanceof Branch) {
            $this->Branch = $branch;
        } elseif (is_string($branch)) {
            $this->Branch = new Branch($branch);
        } else {
            throw new \InvalidArgumentException();
        }

        return $this;
    }

    /**
     * @param string|Commit $commit
     * @return self
     */
    private function setHead(/*string|Commit */$commit): self
    {
        if (is_string($commit)) {
            $commit = $this->getCommit($commit);
        }

        if ($commit instanceof Commit) {
            $this->HEAD = $commit;
        }

        return $this;
    }

    /**
     * Set the upstream branch of the current (checked-out) branch
     *
     * @param string $branch
     * @return self
     */
    private function setUpstreamBranch(string $branch): self
    {
        $this->upstream_branch = $branch;

        return $this;
    }

    /**
     * Throw an exception if no git repository found in the configured path.
     *
     * @return void
     * @throws \Exception
     */
    private function validateInitialized()
    {
        if (!$this->isInitialized()) {
            throw new \Exception(sprintf('A git repository was not found in %s', $this->directory));
        }
    }

    /**
     * Throw an exception if no remote repository configured.
     *
     * @return void
     * @throws \Exception
     */
    private function validateHasRemote(?string $name = null)
    {
        if (!$this->remote($name)) {
            throw new \Exception('No configured remote repository.');
        }
    }

    public static function getFileStatus(File $File): ?string
    {
        $index_status = $File->getIndexStatus();
        $worktree_status = $File->getWorktreeStatus();

        // Unmerged paths:
        if ($worktree_status === 'updated but unmerged') {
            return self::STR_UNMERGED_PATHS;
        }

        // Changes to be committed:
        if ($index_status === 'added') {
            return self::STR_CHANGES_TO_BE_COMMITTED;
        }
        if ($index_status === 'deleted') {
            return self::STR_CHANGES_TO_BE_COMMITTED;
        }
        if ($index_status === 'renamed') {
            return self::STR_CHANGES_TO_BE_COMMITTED;
        }
        if ($index_status === 'modified') {
            return self::STR_CHANGES_TO_BE_COMMITTED;
        }

        // Changes not staged for commit:
        if ($worktree_status === 'modified') {
            return self::STR_CHANGES_NOT_STAGED;
        }
        if ($worktree_status === 'deleted') {
            return self::STR_CHANGES_NOT_STAGED;
        }

        // Untracked files:
        if ($worktree_status === 'untracked') {
            return self::STR_UNTRACKED_FILES;
        }

        /*
Changes to be committed:
  (use "git reset HEAD <file>..." to unstage)

	new file:   public/img/CS_logo_white.svg
	new file:   public/img/Virus_Icon_White.svg
	modified:   resources/assets/sass/app.scss
	modified:   resources/views/_common/system-notifications.blade.php

Unmerged paths:
  (use "git reset HEAD <file>..." to unstage)
  (use "git add/rm <file>..." as appropriate to mark resolution)

	deleted by us:   tests/files/RosterMultiSearch.xlsx

Untracked files:
  (use "git add <file>..." to include in what will be committed)

	tests/files/Guardians_ASA_staging_10.csv
	tests/files/Marvel_nmebu_staging_10.csv
	tests/files/asa_upload_ERROR.csv
	tests/files/non_mebu_upload_huge.csv
	tests/files/~$RosterMultiSearch.xlsx

        */

        var_export([
            'path' => $File->getPath(),
            'index_status' => $index_status,
            'worktree_status' => $worktree_status,
            'original_path' => $File->getOriginalPath()
        ]);

        return null;
    }

    public function Logger(): ?Logger
    {
        if (!isset($this->Logger)) {
            if ($logger = $this->config('core.logger')) {
                // should point to 'logger.'.$logger in the main app config
                $this->Logger = Log::instance($logger);
            } else {
                $this->Logger = NullLogger::create();
            }
        }
        
        return $this->Logger;
    }

    private function _log(array $data, string $level = Logger::INFO)
    {
        if (count($data)) {
            $Logger = $this->Logger();
            $Logger->log(str_replace(["\t", "'"], [' ', ''], implode("\n", $data)), $level);
            
            if ($Logger instanceof BufferLogger) {
                $this->notify();
            }
        }
    }
}