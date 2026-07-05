# syntax=docker/dockerfile:1
#
# Runnable demo of git/git-bundle on a bare Symfony skeleton — no theme, no
# app code, just the bundle's own flat templates browsing the libgit2
# repository (the C library that powers the viewer).
#
#   docker build -t git-bundle-demo -f example/Dockerfile .
#   docker run --rm -p 8000:8000 git-bundle-demo
#   → http://localhost:8000/git
#
# libgit2 is cloned by the bundle's RepositoryWarmer on container start —
# watch the streamed progress in the container output.

FROM php:8.4-cli

# --- system deps + php-git2 (libgit2 bindings, RogerGee/php-git2) -----------
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libgit2-dev \
    && rm -rf /var/lib/apt/lists/*

RUN git clone https://github.com/RogerGee/php-git2.git /usr/src/php-git2 \
    && cd /usr/src/php-git2 \
    && phpize \
    && ./configure --with-libgit2=/usr \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable git2 \
    && rm -rf /usr/src/php-git2

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- bare Symfony skeleton + this bundle ------------------------------------
WORKDIR /srv/demo
RUN composer create-project symfony/skeleton . --no-interaction --no-progress \
    && composer require symfony/twig-bundle symfony/security-bundle --no-interaction --no-progress \
    && composer config repositories.git-bundle vcs https://gitlab.glitchr.dev/public-repository/agnostic/git \
    && composer require git/git-bundle:1.0.x-dev --no-interaction --no-progress

# --- wiring: enable the bundle, declare a repository, import the routes -----
RUN sed -i "s#^];#    Git\\\\GitBundle::class => ['all' => true],\n];#" config/bundles.php

COPY <<'EOF' config/packages/git.yaml
git:
    route_prefix: /git
    access_role: PUBLIC_ACCESS     # demo only — use ROLE_ADMIN in real apps
    repositories:
        libgit2:
            url: 'https://github.com/libgit2/libgit2'
            path: '%kernel.project_dir%/var/repos/libgit2.git'
            label: 'libgit2/libgit2'
            description: 'The C library powering this very viewer'
            default_branch: main
EOF

COPY <<'EOF' config/routes/git.yaml
git:
    resource: '@GitBundle/Resources/config/routes.xml'
EOF

EXPOSE 8000

# cache:warmup triggers the RepositoryWarmer (auto-clone of libgit2), then a
# plain PHP dev server serves the skeleton.
CMD ["sh", "-c", "php bin/console cache:warmup && php -S 0.0.0.0:8000 -t public"]
