<?php

use PhpCliChat\Client\Command;
use PhpCliChat\Client\CommandList;

it('describes every command it knows', function () {
    expect(CommandList::lines())->toBe([
        '/help — show this list',
        '/quit — close the client, like Esc',
    ]);
});

it('takes each name from its constant', function () {
    // The dispatch in ChatClient matches on the same constants, so a renamed
    // command cannot leave the help text behind.
    expect(CommandList::lines()[0])->toStartWith('/' . Command::HELP . ' ');
    expect(CommandList::lines()[1])->toStartWith('/' . Command::QUIT . ' ');
});
