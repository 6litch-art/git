<?php

namespace Git\Service;

use Git\Model\CommitInfo;
use Git\Model\TreeEntry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Git2Service
{
    /** @var array<string, array{path: string, label: string|null, description: string|null, default_branch: string}> */
    private array $repositories;

    public function __construct(array $repositories)
    {
        $this->repositories = $repositories;
    }

    /** @return array<string, array> */
    public function listRepositories(): array
    {
        return $this->repositories;
    }

    public function getRepositoryConfig(string $name): array
    {
        if (!isset($this->repositories[$name])) {
            throw new NotFoundHttpException("Repository '$name' not found.");
        }
        return $this->repositories[$name];
    }

    private function openRepo(string $name)
    {
        $config = $this->getRepositoryConfig($name);
        $repo = git_repository_open($config['path']);
        if (!$repo) {
            throw new \RuntimeException("Cannot open repository at {$config['path']}");
        }
        return $repo;
    }

    /**
     * Resolve a ref (branch name, tag name, abbreviated SHA, "HEAD") to a full 40-char SHA.
     */
    public function resolveRef(string $repoName, string $ref): string
    {
        $repo = $this->openRepo($repoName);

        // Try as arbitrary revspec — handles branch names, tag names, short SHAs, HEAD, etc.
        $obj = @git_revparse_single($repo, $ref);
        if ($obj) {
            // Dereference annotated tags to the target commit
            if (git_object_type($obj) === GIT_OBJ_TAG) {
                try {
                    $peeled = git_object_peel($obj, GIT_OBJ_COMMIT);
                    return git_object_id($peeled);
                } catch (\Throwable) {
                    // fall through and return tag OID
                }
            }
            return git_object_id($obj);
        }

        throw new NotFoundHttpException("Ref '$ref' not found in repository '$repoName'.");
    }

    public function defaultRef(string $repoName): string
    {
        $config = $this->getRepositoryConfig($repoName);
        return $this->resolveRef($repoName, $config['default_branch']);
    }

    /**
     * @return CommitInfo[]
     */
    public function getCommitLog(string $repoName, string $ref, int $limit = 30, int $offset = 0): array
    {
        $repo = $this->openRepo($repoName);
        $sha  = $this->resolveRef($repoName, $ref);

        $walk = git_revwalk_new($repo);
        git_revwalk_sorting($walk, GIT_SORT_TIME);
        git_revwalk_push($walk, $sha);

        $commits = [];
        $skipped = 0;
        while (($oid = git_revwalk_next($walk)) !== null && $oid !== false) {
            if ($skipped < $offset) {
                $skipped++;
                continue;
            }
            $commits[] = $this->commitInfoFromSha($repo, $oid);
            if (count($commits) >= $limit) {
                break;
            }
        }

        git_revwalk_free($walk);
        return $commits;
    }

    /**
     * Return a single CommitInfo including unified diff and stats.
     */
    public function getCommit(string $repoName, string $sha): CommitInfo
    {
        // git_commit_lookup zero-pads abbreviated ids; resolve them first.
        $sha  = $this->resolveRef($repoName, $sha);
        $repo = $this->openRepo($repoName);
        $info = $this->commitInfoFromSha($repo, $sha);

        $commitObj = git_commit_lookup($repo, $sha);
        $tree      = git_commit_tree($commitObj);

        $diff  = null;
        $stats = [];

        if (count($info->parentShas) > 0) {
            $parentCommit = git_commit_lookup($repo, $info->parentShas[0]);
            $parentTree   = git_commit_tree($parentCommit);
            $diffObj = git_diff_tree_to_tree($repo, $parentTree, $tree, []);
        } else {
            $diffObj = git_diff_tree_to_tree($repo, null, $tree, []);
        }

        if ($diffObj) {
            $diff     = git_diff_to_buf($diffObj, GIT_DIFF_FORMAT_PATCH);
            $statsObj = git_diff_get_stats($diffObj);
            if ($statsObj) {
                $stats = [
                    'files_changed' => git_diff_stats_files_changed($statsObj),
                    'insertions'    => git_diff_stats_insertions($statsObj),
                    'deletions'     => git_diff_stats_deletions($statsObj),
                ];
            }
        }

        return new CommitInfo(
            sha:           $info->sha,
            shortSha:      $info->shortSha,
            message:       $info->message,
            subject:       $info->subject,
            authorName:    $info->authorName,
            authorEmail:   $info->authorEmail,
            authorDate:    $info->authorDate,
            committerName: $info->committerName,
            committerEmail:$info->committerEmail,
            committerDate: $info->committerDate,
            parentShas:    $info->parentShas,
            diff:          $diff,
            stats:         $stats,
        );
    }

    /**
     * @return TreeEntry[]
     */
    public function getTree(string $repoName, string $ref, string $path = ''): array
    {
        $repo = $this->openRepo($repoName);
        $sha  = $this->resolveRef($repoName, $ref);

        $commitObj = git_commit_lookup($repo, $sha);
        $tree      = git_commit_tree($commitObj);

        if ($path !== '') {
            $entry = @git_tree_entry_bypath($tree, ltrim($path, '/'));
            if (!$entry) {
                throw new NotFoundHttpException("Path '$path' not found at ref '$ref'.");
            }
            $entryOid = git_tree_entry_id($entry);
            $tree     = git_tree_lookup($repo, $entryOid);
        }

        $count   = git_tree_entrycount($tree);
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $e = git_tree_entry_byindex($tree, $i);
            $entries[] = TreeEntry::fromGit2Entry($e);
        }

        usort($entries, static function (TreeEntry $a, TreeEntry $b): int {
            if ($a->isTree() !== $b->isTree()) {
                return $a->isTree() ? -1 : 1;
            }
            return strcmp($a->name, $b->name);
        });

        return $entries;
    }

    /**
     * Return raw blob content + metadata.
     */
    public function getBlob(string $repoName, string $ref, string $path): array
    {
        $repo = $this->openRepo($repoName);
        $sha  = $this->resolveRef($repoName, $ref);

        $commitObj = git_commit_lookup($repo, $sha);
        $tree      = git_commit_tree($commitObj);

        $entry = @git_tree_entry_bypath($tree, ltrim($path, '/'));
        if (!$entry) {
            throw new NotFoundHttpException("File '$path' not found at ref '$ref'.");
        }

        $blobOid = git_tree_entry_id($entry);
        $blobObj = git_blob_lookup($repo, $blobOid);
        $content = git_blob_rawcontent($blobObj);
        $size    = git_blob_rawsize($blobObj);

        return [
            'content'   => $content,
            'size'      => $size,
            'is_binary' => (bool) git_blob_is_binary($blobObj),
            'sha'       => $blobOid,
        ];
    }

    /**
     * @return array<string, array{name: string, sha: string, is_head: bool}>
     */
    public function getBranches(string $repoName): array
    {
        $repo = $this->openRepo($repoName);

        $head    = @git_repository_head($repo);
        $headSha = null;
        if ($head) {
            $resolved = @git_reference_resolve($head);
            if ($resolved) {
                $headSha = git_reference_target($resolved);
            }
        }

        $allRefs   = git_reference_list($repo);
        $branches  = [];

        foreach ($allRefs as $refName) {
            if (!str_starts_with($refName, 'refs/heads/')) {
                continue;
            }
            $shortName = substr($refName, strlen('refs/heads/'));
            $ref       = git_reference_lookup($repo, $refName);
            $resolved  = git_reference_resolve($ref);
            $sha       = git_reference_target($resolved);
            $branches[$shortName] = [
                'name'    => $shortName,
                'sha'     => $sha,
                'is_head' => $sha === $headSha,
            ];
        }

        ksort($branches);
        return $branches;
    }

    /**
     * @return array<string, array{name: string, sha: string}>
     */
    public function getTags(string $repoName): array
    {
        $repo    = $this->openRepo($repoName);
        $tagList = git_tag_list($repo) ?? [];
        $tags    = [];

        foreach ($tagList as $name) {
            $obj = @git_revparse_single($repo, $name);
            if (!$obj) continue;

            // For annotated tags (GIT_OBJ_TAG), peel to the target commit.
            // For lightweight tags (already a commit), use the OID directly.
            if (git_object_type($obj) === GIT_OBJ_TAG) {
                try {
                    $targetObj = git_object_peel($obj, GIT_OBJ_COMMIT);
                    $sha = git_object_id($targetObj);
                } catch (\Throwable) {
                    $sha = git_object_id($obj);
                }
            } else {
                $sha = git_object_id($obj);
            }

            $tags[$name] = [
                'name' => $name,
                'sha'  => $sha,
            ];
        }

        krsort($tags);
        return $tags;
    }

    /**
     * Return breadcrumb parts for a path string.
     */
    public function pathBreadcrumbs(string $path): array
    {
        if ($path === '') return [];
        $parts      = explode('/', trim($path, '/'));
        $crumbs     = [];
        $cumulative = '';
        foreach ($parts as $part) {
            $cumulative = $cumulative ? "$cumulative/$part" : $part;
            $crumbs[]   = ['name' => $part, 'path' => $cumulative];
        }
        return $crumbs;
    }

    private function commitInfoFromSha($repo, string $sha): CommitInfo
    {
        $commit    = git_commit_lookup($repo, $sha);
        $author    = git2_signature_convert(git_commit_author($commit));
        $committer = git2_signature_convert(git_commit_committer($commit));
        $message   = git_commit_message($commit);
        $subject   = explode("\n", $message, 2)[0];

        $parents      = [];
        $parentCount  = git_commit_parentcount($commit);
        for ($i = 0; $i < $parentCount; $i++) {
            $parents[] = git_commit_parent_id($commit, $i);
        }

        return new CommitInfo(
            sha:           $sha,
            shortSha:      substr($sha, 0, 8),
            message:       rtrim($message),
            subject:       rtrim($subject),
            authorName:    $author['name'],
            authorEmail:   $author['email'],
            authorDate:    new \DateTimeImmutable('@' . $author['when.time']),
            committerName: $committer['name'],
            committerEmail:$committer['email'],
            committerDate: new \DateTimeImmutable('@' . $committer['when.time']),
            parentShas:    $parents,
        );
    }
}
