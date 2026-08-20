# php-cli-chat

A simple terminal chat: an async TCP server and a TUI client, built on
[AMPHP](https://amphp.org) and [Symfony TUI](https://symfony.com/doc/current/tui.html).

```
 Connected to 127.0.0.1:1337. Type /help for commands.
 *** you are now alice
 me: hey there
 bob: hello back

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

## Server

Start it in one terminal:

```bash
php bin/server.php                 # listens on 127.0.0.1:1337
php bin/server.php --host=192.168.1.46
php bin/server.php --port=9000
php bin/server.php --debug         # also print every line exchanged with clients
```

`Ctrl+C` closes every client connection and exits.

Settings a machine keeps can live in `.server.env` instead of the command line:

```bash
cp .server.env.example .server.env   # then edit it
```

```ini
HOST=0.0.0.0
PORT=9000
```

## Client

Connect to a running server (repeat for as many clients as you like):

```bash
php bin/client.php                      # connects to 127.0.0.1:1337
php bin/client.php --host=192.168.1.46
php bin/client.php --host=my-laptop     # a name works here, if DNS knows it
```

Type a message and press Enter. `Esc`, `Ctrl+C` or `/quit` quits the client.

The client reads `.client.env` the same way, for the server it connects to:

```bash
cp .client.env.example .client.env   # then edit it
```

```ini
HOST=my-laptop
PORT=9000
```

### Commands

Anything you type that starts with `/` is a command rather than chat:

| Command             | What it does                                            |
|---------------------|---------------------------------------------------------|
| `/help`             | Lists the commands, in your own log.                    |
| `/login <username>` | Sets the name peers see. One at a time.                 |
| `/logout`           | Gives the name up; peers see `Anonymous` again.         |
| `/quit`             | Closes the client, exactly as `Esc` or `Ctrl + C` does. |

An unrecognized command is reported in your own log and sent to nobody

## Protocol

Client and server exchange one JSON object per line, UTF-8.

```
c→s   {"type":"chat","text":"hello everyone"}
c→s   {"type":"login","name":"alice"}
c→s   {"type":"logout"}
s→c   {"type":"chat","from":"alice","text":"hello everyone"}
s→c   {"type":"notice","text":"you are now alice"}
```

Anything else — invalid JSON, an unknown `type`, a missing or wrong-typed field — is dropped. The server logs it and the
connection stays open.

## Development

```bash
composer test    # Pest
composer stan    # PHPStan at --level=max over src/ and bin/
```

## Known limitations

- `symfony/tui` is an experimental Symfony component with no backwards compatibility promise, hence the `~8.1.0` pin.
- A name lasts as long as its connection and is not persisted.
- A name is ASCII letters, digits and spaces only, at most 20 characters, so `José` and `さくら` are refused.
- No authentication and no TLS. It binds to localhost unless you pass `--host`
- Chat text has no length limit, and the server accepts as many clients as the OS will give it.

## Planned features

- Authentication
- Allow creations of rooms, and join them
- Add persistence to chats
- Add join and leave notices
- Add server commands

## License

MIT
