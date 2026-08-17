<?php

namespace PhpCliChat\Client;

use PhpCliChat\Protocol\MessageStream;

use function Amp\async;

readonly class ChatClient
{
    public function __construct(
        private Ui $ui = new Ui(),
    ) {}

    public function connect(string $address): void
    {
        $stream = MessageStream::connect($address);

        # Client -> Server
        $this->ui->onSubmit(function (string $message) use ($stream) {
            $this->ui->append("me: $message");
            $stream->send($message);
        });

        $this->ui->append("Connected to $address. Esc or Ctrl+C to quit.");

        # Server -> Client
        async(fn() => $this->readFromServer($stream))->ignore();

        $this->ui->run();

        $stream->close();
    }

    private function readFromServer(MessageStream $stream): void
    {
        try {
            foreach ($stream->receive() as $message) {
                $this->ui->append($message);
            }

            $this->ui->append('*** connection closed by server');
        } finally {
            $this->ui->stop();
        }
    }
}
