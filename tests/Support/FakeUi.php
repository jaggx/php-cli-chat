<?php

namespace Tests\Support;

use Amp\DeferredFuture;
use PhpCliChat\Client\Ui;

/**
 * A Ui that records instead of drawing.
 *
 * Constructing the real parent is harmless: Tui touches the terminal and the
 * event loop only in start(), which run() below never reaches.
 */
class FakeUi extends Ui
{
    /**
     * @var string[]
     */
    public array $appended = [];

    public bool $stopped = false;

    /**
     * @var (callable(string): void)|null
     */
    private $onSubmit = null;

    private DeferredFuture $running;

    public function __construct()
    {
        parent::__construct();

        $this->running = new DeferredFuture();
    }

    public function onSubmit(callable $callback): void
    {
        $this->onSubmit = $callback;
    }

    /**
     * Pretends the user typed a line and hit enter. Note this bypasses the
     * real Ui's trimming and blank-line guard, which live in the widget
     * callback rather than here.
     */
    public function submit(string $message): void
    {
        if (null === $this->onSubmit) {
            throw new \LogicException('Nothing registered via onSubmit().');
        }

        ($this->onSubmit)($message);
    }

    public function append(string $line): void
    {
        $this->appended[] = $line;
    }

    public function run(): void
    {
        $this->running->getFuture()->await();
    }

    public function stop(): void
    {
        $this->stopped = true;

        if (!$this->running->isComplete()) {
            $this->running->complete();
        }
    }
}
