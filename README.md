![CI Workflow](https://github.com/bpolaszek/mercure-php-hub/workflows/CI%20Workflow/badge.svg)
[![codecov](https://codecov.io/gh/bpolaszek/mercure-php-hub/branch/master/graph/badge.svg)](https://codecov.io/gh/bpolaszek/mercure-php-hub)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bpolaszek/mercure-php-hub/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bpolaszek/mercure-php-hub/?branch=master)

# Mercure PHP Hub

This POC was a PHP implementation of the [Mercure Hub Specification](https://mercure.rocks/spec).

This repository will no longer be maintained, as I recently released [Freddie](https://github.com/bpolaszek/freddie), which is a brand new implementation leveraging [Framework X](https://framework-x.org/) (still using ReactPHP under the hood).

## Installation

PHP 7.4+ (and Redis, or a Redis instance, if using the Redis transport) is required to run the hub.

```bash
composer create-project bentools/mercure-php-hub:dev-master
```

## Usage

```bash
./bin/mercure --jwt-key=\!ChangeMe\!
```

You can use environment variables (UPPER_SNAKE_CASE) to replace CLI options for better convenience. 
The hub will also check for the presence of a `/etc/mercure/mercure.env` file, 
then make use of the [Symfony DotEnv](https://github.com/symfony/dotenv) component to populate variables.

Check out [configuration.php](src/Configuration/Configuration.php#L20) for full configuration options.


## Advantages and limitations

This implementation does not provide SSL nor HTTP2 termination, so you'd better put a reverse proxy in front of it. 

Example with nginx:

```nginx
upstream mercure {
    server 127.0.0.1:3000;
}

server {
    
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/ssl/certs/example.com/example.com.cert;
    ssl_certificate_key /etc/ssl/certs/example.com/example.com.key;
    ssl_ciphers EECDH+CHACHA20:EECDH+AES128:RSA+AES128:EECDH+AES256:RSA+AES256:EECDH+3DES:RSA+3DES:!MD5;

    location /.well-known/mercure {
        proxy_pass http://mercure;
        proxy_read_timeout 24h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

By default, the hub will run as a simple event-dispatcher. It can fit common needs for a basic usage, but is not 
scalable (opening another process won't share the same event emitter).

On the other hand, you can launch the hub on **multiple ports** and/or **multiple servers** with a Redis transport
(as soon as they share the same Redis instance) and leverage a load-balancer to distribute the traffic. 
This is currently not possible with the open-source Go implementation of the hub because of concurrency restrictions 
on the _bolt_ transport.

```nginx
upstream mercure {
    # 4 instances on 10.1.2.3
    server 10.1.2.3:3000;
    server 10.1.2.3:3001;
    server 10.1.2.3:3002;
    server 10.1.2.3:3003;

    # 4 instances on 10.1.2.4
    server 10.1.2.4:3000;
    server 10.1.2.4:3001;
    server 10.1.2.4:3002;
    server 10.1.2.4:3003;
}
```

```bash
./bin/mercure --transport-url="redis://localhost" --jwt-key=\!ChangeMe\!
```

### Benchmarks

Simulated 1 / 100 /1000 subscribers on server A, 1 publisher blasting messages on server B, Hub on server C (and D for the latter).

| Implementation          | Transport      | Servers | Nodes | 1 subscriber | 100 subscribers | 1000 subscribers |
| ----------------------- | -------------- | ------- | ----- | ------------ | --------------- | ---------------- |
| Mercure.rocks GO Hub    | Bolt           |       1 |     1 |    361 / 286 |      129 / 4989 |       142 /  682 |
| ReactPHP implementation | PHP            |       1 |     1 |    860 / 295 |      519 / 4526 |        45 /  322 |
| ReactPHP implementation | Redis (local)  |       1 |     1 |   1548 / 411 |      393 / 5861 |       112 /  777 |
| ReactPHP implementation | Redis (local)  |       1 |     4 |    108 /  76 |       61 / 2852 |        61 /  688 |
| ReactPHP implementation | Redis (shared) |       2 |     8 |   3035 / 144 |     1183 / 7864 |       708 / 2698 |

Units are `POSTs (publish) / s` / `Received events / s` (total for all subscribers).

Nodes are the total number of ReactPHP open ports.

Hub was hosted on cheap server(s): 2GB / 2 CPU VPS (Ubuntu 20.04). 
You could probably reach a very high level of performance with better-sized servers and dedicated CPUs.

## Feature coverage

| Feature | Covered |
| ------- | ------- |
| JWT through `Authorization` header | ✅ |
| JWT through `mercureAuthorization` Cookie | ✅ |
| Different JWTs for subscribers / publishers | ✅ |
| Allow anonymous subscribers | ✅ |
| CORS | ✅ |
| Private updates | ✅ |
| URI Templates for topics | ✅ |
| Health check endpoint | ✅ |
| HMAC SHA256 JWT signatures | ✅ |
| RS512 JWT signatures | ✅ |
| Environment variables configuration | ✅ |
| Custom message IDs | ✅ |
| Last event ID | ✅️ (except: `earliest` on REDIS transport) |
| Customizable event type | ✅️ |
| Customizable `retry` directive | ✅️ |
| Logging | ❌ (WIP)️ |
| Metrics | ❌ (WIP)️ |
| Subscription events | ❌️ |
| Subscription API | ❌️ |
| Configuration w/ config file | ❌️ |
| Payload | ❌️ |
| Heartbeat | ❌️ |
| `Forwarded` / `X-Forwarded-For` headers | ❌️ |
| Alternate topics | ❌️ |

## Additional features

This implementation provides features which are not defined in the original specification.

### Subscribe / Publish topic exclusions

Mercure leverages [URI Templates](https://tools.ietf.org/html/rfc6570) to grant subscribe and/or publish auhorizations 
on an URI pattern basis:
```json
{
  "mercure": {
    "publish": [
      "https://example.com/items/{id}"
    ],
    "subscribe": [
      "https://example.com/items/{id}"
    ]
  }
}
```

However, denying access to a specific URL matching an URI template requires you to explicitely list authorized items:
```json
{
  "mercure": {
    "publish": [
      "https://example.com/items/1",
      "https://example.com/items/2",
      "https://example.com/items/4"
    ],
    "subscribe": [
      "https://example.com/items/1",
      "https://example.com/items/2",
      "https://example.com/items/4"
    ]
  }
}
```

When dealing with thousands of possibilities, it can quicky become a problem. The Mercure PHP Hub allows you to specify
denylists through the `publish_exclude` and `subscribe_exclude` keys, which accept any topic selector:
```json
{
  "mercure": {
    "publish": [
      "https://example.com/items/{id}"
    ],
    "publish_exclude": [
      "https://example.com/items/3"
    ],
    "subscribe": [
      "https://example.com/items/{id}"
    ],
    "subscribe_exclude": [
      "https://example.com/items/3"
    ]
  }
}
```

### Json Web Token Generator

You can generate a JWT to use on the hub from the command-line:

```bash
./bin/mercure jwt:generate
```

It will ask you interactively what topic selectors you want to allow/deny for publishing/subscribing, and ask you 
for an optional TTL.

If you want a raw output (to pipe the generated JWT for instance), use the `--raw` option.

To disable interaction, you can use the following example:


```bash
./bin/mercure jwt:generate --no-interactive --publish=/foo/{id} --publish=/bar --publish-exclude=/foo/bar
```

It will use your JWT keys environment variables, or you can use the `--jwt-key`, `--publisher-jwt-key`, `--subscriber-jwt-key` options.

For a full list of available options, run this:

```bash
./bin/mercure jwt:generate -h
```

## Tests

This project is covered with [Pest](https://pestphp.com/) tests. 
Coverage has to be improved: feel free to contribute.

```bash
composer tests:run
```

## Contribute

If you want to improve this project, feel free to submit PRs:

- CI will yell if you don't follow [PSR-12 coding standards](https://www.php-fig.org/psr/psr-12/)
- In the case of a new feature, it must come along with tests
- [PHPStan](https://phpstan.org/) analysis must pass at level 5

You can run `composer ci:check` before committing to ensure all CI requirements are successfully met.

## License

GNU General Public License v3.0.
