# Boot-test image: WordPress, wp-cli, and the plugin staged for installation.
#
# The build context is the repository root, because the plugin is copied in from
# a build context rather than bind-mounted. A CI runner is itself a container,
# so "$PWD" is not a path the docker daemon can resolve and a bind mount lands
# empty — the trap .gitea/workflows/ci.yml already documents.
#
# The COPY list below is deliberately the same set of paths as RELEASE_PATHS in
# scripts/build-release.php, so the boot test exercises what actually ships
# rather than the whole working tree. tests/wordpress/run.sh fails if the two
# lists drift apart.

FROM wordpress:6.7-php8.3-apache

# Pinned so a wp-cli release cannot change what CI does overnight. ADD avoids
# depending on curl being present in the base image; the separate chmod keeps
# this working on builders that predate ADD --chmod.
ADD https://github.com/wp-cli/wp-cli/releases/download/v2.11.0/wp-cli-2.11.0.phar /usr/local/bin/wp
# An explicit 0755, not "chmod +x": a remote ADD lands as 0600, so +x alone
# leaves it unreadable by the web user that run.sh drives wp-cli as.
RUN chmod 0755 /usr/local/bin/wp

# Staged outside the web root and copied into place by run.sh once WordPress is
# installed, so the image never depends on how the WordPress entrypoint treats
# pre-existing files under /var/www/html.
COPY assets /usr/src/bci-plugin/assets
COPY includes /usr/src/bci-plugin/includes
COPY readme.txt /usr/src/bci-plugin/readme.txt
COPY woocommerce-gateway-bci.php /usr/src/bci-plugin/woocommerce-gateway-bci.php
COPY docs/merchant-setup-guide.md /usr/src/bci-plugin/docs/merchant-setup-guide.md
COPY docs/images/setup /usr/src/bci-plugin/docs/images/setup

COPY tests/wordpress/assertions.php /usr/src/bci-assertions.php
