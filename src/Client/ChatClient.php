<?php

namespace PhpCliChat\Client;

use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Message\Login;
use PhpCliChat\Protocol\Message\Logout;
use PhpCliChat\Protocol\Message\Notice;
use PhpCliChat\Protocol\MessageChannel;
use PhpCliChat\Protocol\Transport\LineStream;

use function Amp\async;

class ChatClient
{
    private ClientOptions $options;
    private MessageChannel $channel;

    private Ui $ui;

    public function __construct()
    {
        $this->options = new ClientOptions();
        $this->ui = new Ui();
    }

    public function setOptions(ClientOptions $options): void
    {
        $this->options = $options;
    }

    public function setUi(Ui $ui): void
    {
        $this->ui = $ui;
    }

    public function connect(): void
    {
        $address = "{$this->options->host}:{$this->options->port}";

        $this->channel = MessageChannel::forClient(LineStream::connect($address));

        # Client -> Server
        $this->ui->onSubmit(function (string $message) {
            $command = Command::parse($message);

            if (Command::CHAT === $command) {
                $this->ui->append("me: $message");
                $this->channel->send(new Chat($message));

                return;
            }

            $this->run($command);
        });

        $this->ui->append("Connected to $address. Type /help for commands.");

        # Server -> Client
        async(fn() => $this->readFromServer())->ignore();

        $this->ui->run();

        $this->channel->close();
    }

    private function run(Command $command): void
    {
        match ($command->name) {
            Command::HELP   => $this->help(),
            Command::LOGIN  => $this->login($command->args),
            Command::LOGOUT => $this->channel->send(new Logout()),
            Command::QUIT   => $this->ui->stop(),
            default         => $this->ui->append('*** unknown command: /' . $this->truncate($command->name)),
        };
    }

    private function help(): void
    {
        // One append per line: Sanitizer strips an embedded newline.
        foreach (CommandList::lines() as $line) {
            $this->ui->append("*** $line");
        }
    }

    private function login(string $name): void
    {
        if ('' === $name) {
            $this->ui->append('*** usage: /login <username>');

            return;
        }

        // No local echo and no validation: the server's notice is the feedback,
        // and the rules are its to enforce.
        $this->channel->send(new Login($name));
    }

    private function truncate(string $name): string
    {
        // @ keeps iconv's E_NOTICE on invalid UTF-8 out of the TUI's terminal.
        $cut = @iconv_substr($name, 0, 40);

        return false === $cut ? '' : $cut;
    }

    private function readFromServer(): void
    {
        try {
            foreach ($this->channel->receive() as $message) {
                if ($message instanceof Broadcast) {
                    $this->ui->append("$message->from: $message->text");
                }

                if ($message instanceof Notice) {
                    $this->ui->append("*** $message->text");
                }
            }

            $this->ui->append('*** connection closed by server');
        } finally {
            $this->ui->stop();
        }
    }
}
