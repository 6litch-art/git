# Git Bundle

A GitHub-like, **read-only git repository viewer** embedded in your Symfony
application — browse trees, blobs, commit history, diffs, branches and tags of
any local repository from your own site, behind your own security layer.

## Philosophy

- **In-process, powered by libgit2.** All repository reads go through
  [libgit2](https://libgit2.org) via the [php-git2](https://github.com/RogerGee/php-git2)
  PHP extension. Requests never shell out to the `git` binary and never talk to
  an external service (no gitweb/cgit/GitLab needed): a repository is treated
  as a plain data source, opened and object-read directly by PHP.
- **Read-only by construction.** The bundle exposes lookups only (trees, blobs,
  commits, refs). Nothing writes to the repositories.
- **Safe by object addressing.** Paths in URLs are resolved through git tree
  *objects*, never through the filesystem — path traversal outside the
  repository is impossible by design. Every route is additionally gated by a
  configurable role.
- **Repositories are declarative.** You list repositories in config; anything
  declared with a `url` whose `path` does not exist yet is **cloned
  automatically** during `cache:warmup`, and fetched (`--all --prune`) on later
  warmups. Deploying a new viewer is: add 4 lines of YAML, warm the cache.

## Try it in one command

A self-contained demo (bare Symfony skeleton + this bundle, browsing the
libgit2 repository itself) ships in [example/Dockerfile](example/Dockerfile):

```bash
docker build -t git-bundle-demo -f example/Dockerfile .
docker run --rm -p 8000:8000 git-bundle-demo
# → http://localhost:8000/git   (libgit2 is auto-cloned at startup by the warmer)
```

## Requirements

- PHP ≥ 8.1
- The **php-git2 extension** (PHP bindings for the libgit2 C library). It is
  not bundled with PHP; install libgit2 then build the extension:

  ```bash
  apt install libgit2-dev            # or brew install libgit2
  git clone https://github.com/RogerGee/php-git2 && cd php-git2
  phpize && ./configure --with-libgit2=/usr && make && make install
  docker-php-ext-enable git2        # or add "extension=git2.so" to php.ini
  ```

## Installation

```bash
composer require git/git-bundle:^1.0
```

Enable it (Flex usually does this) in `config/bundles.php`:

```php
Git\GitBundle::class => ['all' => true],
```

## Configuration

`config/packages/git.yaml`:

```yaml
git:
    route_prefix: /git          # URL prefix for all viewer routes
    access_role: ROLE_ADMIN     # role required to browse (PUBLIC_ACCESS to open up)
    repositories:
        hellogitworld:
            url:  'https://github.com/githubtraining/hellogitworld'   # auto-cloned at cache:warmup
            path: '%kernel.project_dir%/var/repos/hellogitworld.git'
            label: 'Hello Git World'
            description: 'Demo repository'
            default_branch: master
        app:
            path: '%kernel.project_dir%'    # any existing local repo works too
            label: 'This application'
            default_branch: HEAD
```

`config/routes/git.yaml`:

```yaml
git:
    resource: '@GitBundle/Resources/config/routes.xml'
```

(or import the controller directly with `type: attribute` and your own prefix.)

## Routes

| URL | View |
|-----|------|
| `/git` | repository index |
| `/git/{repo}` | redirect to the default branch tree |
| `/git/{repo}/tree/{ref}/{path}` | directory listing |
| `/git/{repo}/blob/{ref}/{path}` | file contents |
| `/git/{repo}/log/{ref}` | commit history (paginated) |
| `/git/{repo}/commit/{sha}` | commit details + diff |
| `/git/{repo}/branches`, `/git/{repo}/tags` | refs |

## Notes

- The auto-clone/fetch happens in a **cache warmer** (`RepositoryWarmer`,
  optional): a slow remote can slow down `cache:warmup`, so prefer bare
  mirrors on fast storage for large repositories.
- Licensed LGPL-3.0-or-later (see `COPYING` / `COPYING.LESSER`).
