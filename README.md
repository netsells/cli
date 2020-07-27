# Netsells CLI

The Netsells Command Line Interface (CLI).

## Table of Contents
- [Installation](#installation)
- [Documentation](#documentation)

## Installation

Run the following commands to download and install. `/usr/local/bin` should be in your `$PATH` in order to call `netsells` anywhere.

```bash
curl -L -o netsells.phar https://netsells-cli.now.sh/download/cli
mv netsells.phar /usr/local/bin/netsells
chmod +x /usr/local/bin/netsells
netsells
```

### Usage

```
 _   _      _            _ _        _____ _      _____
| \ | |    | |          | | |      / ____| |    |_   _|
|  \| | ___| |_ ___  ___| | |___  | |    | |      | |
| . ` |/ _ \ __/ __|/ _ \ | / __| | |    | |      | |
| |\  |  __/ |_\__ \  __/ | \__ \ | |____| |____ _| |_
|_| \_|\___|\__|___/\___|_|_|___/  \_____|______|_____|


  USAGE: netsells <command> [options] [arguments]

  aws:ec2:list             List the instances available
  aws:ssm:connect          Connect to an server via SSH
  aws:ssm:start-session    Configures your machine for SSM SSH connections

  docker:aws:deploy-update Updates task definition and service
  docker:aws:push          Pushes docker-compose created images to ECR.
  docker:build             Builds docker-compose ready for prod.
```

## Netsells File Reference

The CLI will look for configuration in the arguments/options supplied via the command line, falling back to the Netsells file. This should be placed at the root of your project and called `.netsells.yml`.

Below is a feature complete file to be used as reference:

```yaml
docker:
    services:
        - web
        - php
    aws:
        region: eu-west-2
        ecs:
            cluster: traefik
            service: netsells-api
            task-definition: netsells-api

            # If set, will spin up a migrate task to run the command during an update
            migrate:
                container: php
                command: LARAVEL_DATABASE_MIGRATIONS # Using a constant
                # command: ["php", "artisan", "migrate", "--force"] # List syntax, same as dockerfile - https://docs.docker.com/engine/reference/builder/#cmd
```
