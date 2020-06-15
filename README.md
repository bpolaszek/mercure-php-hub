# Mercure PHP Hub

This POC is a PHP implementation of the [Mercure Hub Specification](https://mercure.rocks/spec).

It is blazing fast, built on top of [ReactPHP](https://reactphp.org/) and optionally leverages a 
[Redis](https://redis.io/) server for scaling.

It is currently experimental and production use is at your own risk, but feel free to play and improve it!

## Installation

PHP 7.4+ (and Redis, or a Redis instance, if using the Redis transport) is required to run the hub.

```bash
composer create-project bentools/mercure-php-hub
```

## Usage

```bash
./bin/mercure --jwt-key=\!ChangeMe\!
```

You can use environment variables (UPPER_SNAKE_CASE) to replace CLI options for better convenience. 
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

### Feature coverage

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
| Subscription events | ❌️ |
| Subscription API | ❌️ |
| Configuration w/ config file | ❌️ |
| Customizable event type | ❌️ (implemented, but not tested) |
| Customizable `retry` directive | ❌️ (implemented, but not tested) |
| Payload | ❌️ |
| Heartbeat | ❌️ |
| `Forwarded` / `X-Forwarded-For` headers | ❌️ |

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
