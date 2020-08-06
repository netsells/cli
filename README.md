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
  aws:ssm:connect          Connect to an server via SSH (Use --tunnel to establish an SSH tunnel)

  docker:aws:deploy-update Updates task definition and service
  docker:aws:login         Logs into docker via the AWS account
  docker:aws:push          Pushes docker-compose created images to ECR
  docker:build             Builds docker-compose ready for prod
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

## Command Reference

* [aws:ec2:list](#awsec2list) - List the instances available
* [aws:ssm:connect](#awsssmconnect) - Connect to an server via SSH (Use --tunnel to establish an SSH tunnel)


### aws:ec2:list

```
netsells aws:ec2:list
```

Returns a list of ec2 instances on the AWS account

**Available Arguments**
* `--aws-profile=` - Override the AWS profile to use

### aws:ssm:connect

```
netsells aws:ssm:connect
```

Establishes a command to a server over SSH. This command first generates a temporary SSH key, sends it to the server via SSM SendCommand feature which then allows 15 seconds for the SSH client to connect via the SSM session.

If you don't supply any options, you will be asked for them. `--tunnel` is required to establish an SSH tunnel (typically used for MySQL).

**Available Arguments**
* `--aws-profile=` - The name of the AWS profile to use
* `--instance-id=` - The instance ID to connect to
* `--username=` - The username connect with
* `--tunnel` - Sets up an SSH tunnel. Required to initiate a tunnel connection
* `--tunnel-remote-server=` - The SSH tunnel remote server
* `--tunnel-remote-port=` - The SSH tunnel remote port
* `--tunnel-local-port=` - The SSH tunnel local port
* `--aws-profile=` - Override the AWS profile to use
