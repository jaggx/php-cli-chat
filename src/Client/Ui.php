<?php

namespace PhpCliChat\Client;

use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class Ui
{
    private const int HISTORY = 200;

    /**
     * @var string[]
     */
    private array $lines = [];

    private Tui $tui;
    private TextWidget $log;
    private InputWidget $input;

    public function __construct()
    {
        $this->tui = new Tui();
        $this->log = new TextWidget();
        $this->input = new InputWidget();

        $this->input->setPrompt('> ');
        $this->input->onCancel(fn() => $this->stop());

        $this->input->setStyle(
            new Style(
                padding: Padding::xy(1),
                border : Border::from([1], BorderPattern::ROUNDED),
            )
        );

        $log = new ContainerWidget();
        $log->add($this->log);
        $log->expandVertically(true);
        $log->setStyle(new Style(verticalAlign: VerticalAlign::Bottom));

        $this->tui->add($log);
        $this->tui->add($this->input);
        $this->tui->setFocus($this->input);
    }

    /**
     * @param callable(string): void $callback
     */
    public function onSubmit(callable $callback): void
    {
        $this->input->onSubmit(function () use ($callback) {
            $message = trim($this->input->getValue());
            $this->input->setValue('');

            if ('' === $message) {
                return;
            }

            $callback($message);
        });
    }

    public function append(string $line): void
    {
        $this->lines[] = Sanitizer::sanitize($line);
        $this->lines = \array_slice($this->lines, -self::HISTORY);

        $this->log->setText(implode("\n", $this->lines));
        $this->tui->requestRender();
    }

    public function run(): void
    {
        $this->tui->run();
    }

    public function stop(): void
    {
        $this->tui->stop();
    }
}
