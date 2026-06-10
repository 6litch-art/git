<?php

namespace Git\Model;

class TreeEntry
{
    public const TYPE_TREE = 'tree';
    public const TYPE_BLOB = 'blob';
    public const TYPE_SUBMODULE = 'submodule';

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $sha,
        public readonly int $filemode,
        public readonly ?CommitInfo $lastCommit = null,
    ) {}

    public function isTree(): bool
    {
        return $this->type === self::TYPE_TREE;
    }

    public function isBlob(): bool
    {
        return $this->type === self::TYPE_BLOB;
    }

    public static function fromGit2Entry($entry): self
    {
        $filemode = git_tree_entry_filemode($entry);
        $oid      = git_tree_entry_id($entry);
        $name     = git_tree_entry_name($entry);

        $type = match (git_tree_entry_type($entry)) {
            GIT_OBJ_TREE => self::TYPE_TREE,
            GIT_OBJ_BLOB => self::TYPE_BLOB,
            default      => self::TYPE_SUBMODULE,
        };

        return new self(
            name:     $name,
            type:     $type,
            sha:      $oid,
            filemode: $filemode,
        );
    }
}
