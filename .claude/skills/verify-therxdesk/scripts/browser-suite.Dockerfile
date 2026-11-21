# The runner for tests/Browser (see browser-suite.sh): the official Playwright
# image at the version the repo has installed, plus the PHP 8.5 and the
# extensions the CI job installs. Not used by the dev stack.
ARG PLAYWRIGHT_VERSION=1.65.1
FROM mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends software-properties-common ca-certificates curl \
 && add-apt-repository -y ppa:ondrej/php \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
      php8.5-cli php8.5-curl php8.5-xml php8.5-mbstring php8.5-zip \
      php8.5-bcmath php8.5-intl php8.5-gd php8.5-imagick php8.5-pgsql \
      php8.5-mysql php8.5-redis php8.5-sqlite3 \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
