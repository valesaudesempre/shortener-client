# Vale Saude Shortener Client

A simple HTTP Client for Vale Saude Shortener (https://github.com/valesaudesempre/shortener).

### Usage

Install package:

```shell
composer require valesaude/shortener-client:^1.0
```

Set the following configs:

- `services.valesaude.shortener_client.base_uri`: The shortener application base URI.
- `services.valesaude.shortener_client.username`: The application username.
- `services.valesaude.shortener_client.password`: The application password.

Optionally, publish and change the localization files:

```shell
php artisan vendor:publish --tag=valesaude-shortener-client-translation
```