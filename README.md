# php-cli-chat

A simple terminal chat: an async TCP server and a TUI client, built on
[AMPHP](https://amphp.org) and [Symfony TUI](https://symfony.com/doc/current/tui.html).

```
 Connected to 127.0.0.1:1337. Esc or Ctrl+C to quit.
 me: hey there
 client 1: hello back

 ╭──────────────────────────────────────────────────────────╮
 │ >                                                        │
 ╰──────────────────────────────────────────────────────────╯
```

## Requirements

PHP 8.4+, plus `ext-pcntl` for the server's signal handling and
`ext-iconv` for scrubbing malformed UTF-8.

## Install

```bash
composer install
```

## Run

Start the server in one terminal:

```bash
php bin/server.php                 # listens on 127.0.0.1:1337
php bin/server.php --host=192.168.1.46
php bin/server.php --port=9000
```

Connect a client in another (repeat for as many clients as you like):

```bash
php bin/client.php                      # connects to 127.0.0.1:1337
php bin/client.php --host=192.168.1.46
php bin/client.php --host=my-laptop     # a name works here, if DNS knows it
```

Type a message and press Enter. `Esc` or `Ctrl+C` quits the client.

`Ctrl+C` on the server closes every client connection and exits.

## Development

```bash
composer test    # Pest
composer stan    # PHPStan at --level=max over src/ and bin/
```

## Known limitations

- `symfony/tui` is an experimental Symfony component with no backwards compatibility promise, hence the `~8.1.0` pin.
- Peers are identified by connection id only — no nicknames.
- No join or leave notices.
- No authentication and no TLS. It binds to localhost unless you pass
  `--host`

## Planned features

- Add support for nicknames
- Allow creations of rooms, and join them
- Add persistence to chats
- Add server and client commands

## License

MIT
