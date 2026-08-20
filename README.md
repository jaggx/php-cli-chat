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

## Run

Start the server in one terminal:

```bash
php bin/server.php                 # listens on 127.0.0.1:1337
php bin/server.php --host=192.168.1.46
php bin/server.php --port=9000
php bin/server.php --debug         # also print every line exchanged with clients
```

Connect a client in another (repeat for as many clients as you like):

```bash
php bin/client.php                      # connects to 127.0.0.1:1337
php bin/client.php --host=192.168.1.46
php bin/client.php --host=my-laptop     # a name works here, if DNS knows it
```

Type a message and press Enter. `Esc`, `Ctrl+C` or `/quit` quits the client.

`Ctrl+C` on the server closes every client connection and exits.

## Commands

Anything you type that starts with `/` is a command rather than chat:

| Command             | What it does                                            |
|---------------------|---------------------------------------------------------|
| `/help`             | Lists the commands, in your own log.                    |
| `/login <username>` | Sets the name peers see. Once per connection.           |
| `/quit`             | Closes the client, exactly as `Esc` or `Ctrl + C` does. |

An unrecognized command is reported in your own log and sent to nobody

## Protocol

Client and server exchange one JSON object per line, UTF-8.

```
c→s   {"type":"chat","text":"hello everyone"}
c→s   {"type":"login","name":"alice"}
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
- A name lasts as long as its connection and is not persisted. It cannot be changed without reconnecting.
- A name is ASCII letters, digits and spaces only, at most 20 characters, so `José` and `さくら` are refused.
- No authentication and no TLS. It binds to localhost unless you pass `--host`

## Planned features

- Authentication
- Allow creations of rooms, and join them
- Add persistence to chats
- Add join and leave notices
- Add server commands

## License

MIT
