<?php

namespace Git\Controller;

use Git\Service\Git2Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class RepositoryController extends AbstractController
{
    public function __construct(
        private readonly Git2Service $git,
        private readonly string $accessRole = 'ROLE_ADMIN',
    ) {}

    private function checkAccess(): void
    {
        $this->denyAccessUnlessGranted($this->accessRole);
    }

    #[Route('', name: 'git_repositories')]
    public function repositories(): Response
    {
        $this->checkAccess();
        return $this->render('@Git/repositories.html.twig', [
            'repos' => $this->git->listRepositories(),
        ]);
    }

    #[Route('/{repo}', name: 'git_repo_default', requirements: ['repo' => '[^/]+'])]
    public function repoDefault(string $repo): Response
    {
        $this->checkAccess();
        $config = $this->git->getRepositoryConfig($repo);
        return $this->redirectToRoute('git_tree', [
            'repo' => $repo,
            'ref'  => $config['default_branch'],
            'path' => '',
        ]);
    }

    #[Route('/{repo}/tree/{ref}/{path}', name: 'git_tree', requirements: ['repo' => '[^/]+', 'ref' => '[^/]+', 'path' => '.*'], defaults: ['path' => ''])]
    public function tree(string $repo, string $ref, string $path): Response
    {
        $this->checkAccess();
        $entries = $this->git->getTree($repo, $ref, $path);
        $config  = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/tree.html.twig', [
            'repo'        => $repo,
            'config'      => $config,
            'ref'         => $ref,
            'path'        => $path,
            'entries'     => $entries,
            'breadcrumbs' => $this->git->pathBreadcrumbs($path),
        ]);
    }

    #[Route('/{repo}/blob/{ref}/{path}', name: 'git_blob', requirements: ['repo' => '[^/]+', 'ref' => '[^/]+', 'path' => '.+'])]
    public function blob(string $repo, string $ref, string $path): Response
    {
        $this->checkAccess();
        $blob   = $this->git->getBlob($repo, $ref, $path);
        $config = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/blob.html.twig', [
            'repo'        => $repo,
            'config'      => $config,
            'ref'         => $ref,
            'path'        => $path,
            'blob'        => $blob,
            'filename'    => basename($path),
            'breadcrumbs' => $this->git->pathBreadcrumbs($path),
        ]);
    }

    #[Route('/{repo}/log/{ref}', name: 'git_log', requirements: ['repo' => '[^/]+', 'ref' => '[^/]+'])]
    public function log(Request $request, string $repo, string $ref): Response
    {
        $this->checkAccess();
        $page    = max(1, (int) $request->query->get('page', 1));
        $limit   = 30;
        $commits = $this->git->getCommitLog($repo, $ref, $limit, ($page - 1) * $limit);
        $config  = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/log.html.twig', [
            'repo'    => $repo,
            'config'  => $config,
            'ref'     => $ref,
            'commits' => $commits,
            'page'    => $page,
            'has_more'=> count($commits) === $limit,
        ]);
    }

    #[Route('/{repo}/commit/{sha}', name: 'git_commit', requirements: ['repo' => '[^/]+', 'sha' => '[0-9a-f]{7,40}'])]
    public function commit(string $repo, string $sha): Response
    {
        $this->checkAccess();
        $commit = $this->git->getCommit($repo, $sha);
        $config = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/commit.html.twig', [
            'repo'   => $repo,
            'config' => $config,
            'commit' => $commit,
        ]);
    }

    #[Route('/{repo}/branches', name: 'git_branches', requirements: ['repo' => '[^/]+'])]
    public function branches(string $repo): Response
    {
        $this->checkAccess();
        $branches = $this->git->getBranches($repo);
        $config   = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/refs.html.twig', [
            'repo'     => $repo,
            'config'   => $config,
            'branches' => $branches,
            'tags'     => [],
            'active'   => 'branches',
        ]);
    }

    #[Route('/{repo}/tags', name: 'git_tags', requirements: ['repo' => '[^/]+'])]
    public function tags(string $repo): Response
    {
        $this->checkAccess();
        $tags   = $this->git->getTags($repo);
        $config = $this->git->getRepositoryConfig($repo);
        return $this->render('@Git/refs.html.twig', [
            'repo'     => $repo,
            'config'   => $config,
            'branches' => [],
            'tags'     => $tags,
            'active'   => 'tags',
        ]);
    }
}
