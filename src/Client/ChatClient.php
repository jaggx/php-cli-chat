<?php

namespace PhpCliChat\Client;

use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\MessageChannel;
use PhpCliChat\Protocol\Transport\LineStream;

use function Amp\async;

readonly class ChatClient
{
    public function __construct(
        private Ui $ui = new Ui(),
    ) {}

    public function connect(string $address): void
    {
        $channel = MessageChannel::forClient(LineStream::connect($address));

        # Client -> Server
        $this->ui->onSubmit(function (string $message) use ($channel) {
            $this->ui->append("me: $message");
            $channel->send(new Chat($message));
        });

        $this->ui->append("Connected to $address. Esc or Ctrl+C to quit.");

        # Server -> Client
        async(fn() => $this->readFromServer($channel))->ignore();

        $this->ui->run();

        $channel->close();
    }

    private function readFromServer(MessageChannel $channel): void
    {
        try {
            foreach ($channel->receive() as $message) {
                if ($message instanceof Broadcast) {
                    $this->ui->append("client $message->from: $message->text");
                }
            }

            $this->ui->append('*** connection closed by server');
        } finally {
            $this->ui->stop();
        }
    }
}
