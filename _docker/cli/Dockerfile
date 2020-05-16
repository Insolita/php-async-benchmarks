FROM php:7.4-cli
# Prepare Debian environment
ENV DEBIAN_FRONTEND noninteractive

# Performance optimization - see https://gist.github.com/jpetazzo/6127116
# this forces dpkg not to call sync() after package extraction and speeds up install
RUN echo "force-unsafe-io" > /etc/dpkg/dpkg.cfg.d/02apt-speedup
# we don't need and apt cache in a container
RUN echo "Acquire::http {No-Cache=True;};" > /etc/apt/apt.conf.d/no-cache

ARG USERNAME=appuser
ARG GROUP=appuser
ARG PASSWD=pass
ARG UID=1000
ARG GID=1000
ARG HOME=/home/$USERNAME
ARG ALLOW_SUDO=1

RUN  apt-get update && \
     apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpq-dev \
        libssl-dev \
        libnghttp2-dev \
        g++ \
        sudo \
        git \
        unzip\
        libxml2-dev \
            && docker-php-ext-install -j$(nproc) pcntl \
            && docker-php-ext-install -j$(nproc) zip \
            && docker-php-ext-install -j$(nproc) pgsql \
            && docker-php-ext-install -j$(nproc) pdo_pgsql \
            && docker-php-ext-install -j$(nproc) sockets \
            && docker-php-ext-install -j$(nproc) xmlrpc \
            && docker-php-ext-install -j$(nproc) opcache \
            && docker-php-ext-enable opcache \
            && apt-get clean \
            && curl -o /tmp/swoole.tar.gz https://github.com/swoole/swoole-src/archive/v4.5.1.tar.gz -L \
            && tar zxvf /tmp/swoole.tar.gz && cd swoole-src* \
            && phpize && ./configure --enable-openssl  --enable-http2 && make && make install \
            && docker-php-ext-enable swoole \
            && curl -o /tmp/swoolepg.tar.gz https://github.com/swoole/ext-postgresql/archive/v4.5.1.tar.gz -L \
            && tar zxvf /tmp/swoolepg.tar.gz && cd swoole-src* \
            && phpize && ./configure && make && make install\
            && docker-php-ext-enable swoole_postgresql  \
            && echo "swoole.fast_serialize=On" >> /usr/local/etc/php/conf.d/docker-php-ext-swoole-serialize.ini  \
            &&  rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN groupadd --gid $GID $GROUP \
            && useradd --groups ${GROUP},sudo \
            --uid $UID --gid $GID --shell /bin/bash --create-home --home $HOME $USERNAME \
            && echo "${PASSWD}\n${PASSWD}\n"|passwd  $USERNAME &&\
            if [ $ALLOW_SUDO -eq 1 ]; then echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers; fi

# Initialize application
WORKDIR /app

# Install composer
ENV COMPOSER_HOME $HOME/.composer/
ENV PATH $HOME/.composer/vendor/bin:$PATH

RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
&& curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
# Make sure we're installing what we think we're installing!
&& php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
&& php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --snapshot \
&& rm -f /tmp/composer-setup.* \
&& chown -R $USERNAME:$USERNAME $HOME


USER $USERNAME
