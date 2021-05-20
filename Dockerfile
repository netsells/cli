##
# Composer
##
FROM composer:latest as composer

##
# PHP Builder
##
FROM php:7.4-cli as build

ARG DOCKER_TAG

# Deps
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

# Grab built phar from the builder
COPY --from=build /app/builds/netsells /usr/local/bin/netsells

# Copy the wrapper from source
COPY ./docker-support/netsells /usr/local/bin/netsells-wrapper

# Deps
RUN apt-get update && apt-get install -y \
  unzip \
  git \
  docker.io

# AWS CLI
RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" && unzip awscliv2.zip && ./aws/install && rm awscliv2.zip

# Session Manager
RUN curl "https://s3.amazonaws.com/session-manager-downloads/plugin/latest/ubuntu_64bit/session-manager-plugin.deb" -o "session-manager-plugin.deb" && dpkg -i session-manager-plugin.deb && rm session-manager-plugin.deb

RUN mkdir /app
WORKDIR /app

ENTRYPOINT ["netsells"]
