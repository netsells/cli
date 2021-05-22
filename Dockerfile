##
# Composer
##
FROM composer:latest as composer

##
# PHP Builder
##
FROM php:7.4-cli as build

ARG DOCKER_TAG

# Deps
RUN apt-get update && apt-get install -y \
  unzip \
  git

# Grab composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY . /app
WORKDIR /app

# Install CLI deps
RUN composer install --ansi --no-interaction --no-progress --prefer-dist

# Build the phar
RUN php netsells app:build --build-version=$DOCKER_TAG

##
# PHP Runtime
##
FROM php:7.4-cli as runtime

# Deps
RUN apt-get update && \
  apt-get install -y --no-install-recommends \
    unzip git openssh-client amazon-ecr-credential-helper && \
  apt-get purge -y autoconf pkg-config gcc && \
  apt-get autoremove -y && \
  apt-get autoclean && \
  apt-get clean && \
  curl -L "https://github.com/docker/compose/releases/download/1.29.2/docker-compose-$(uname -s)-$(uname -m)" \
    -o /usr/local/bin/docker-compose && chmod +x /usr/local/bin/docker-compose && \
  curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" \
    -o "awscliv2.zip" && unzip -q awscliv2.zip && ./aws/install && rm awscliv2.zip && rm -rf ./aws && \
  curl "https://s3.amazonaws.com/session-manager-downloads/plugin/latest/ubuntu_64bit/session-manager-plugin.deb" \
    -o "session-manager-plugin.deb" && dpkg -i session-manager-plugin.deb && rm session-manager-plugin.deb && \
  rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Grab docker client from existing docker image
COPY --from=docker:20-dind /usr/local/bin/docker /usr/local/bin/docker

# Copy the docker config to use ecr auth
COPY ./docker-support/docker-config.json /root/.docker/config.json

# Copy the wrapper from source
COPY ./docker-support/netsells /usr/local/bin/netsells-wrapper

# Grab built phar from the builder
COPY --from=build /app/builds/netsells /usr/local/bin/netsells

ENV AWS_SDK_LOAD_CONFIG 1

RUN mkdir /app
WORKDIR /app

ENTRYPOINT ["netsells"]
