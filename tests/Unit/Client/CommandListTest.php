<?php

use PhpCliChat\Client\Command;
use PhpCliChat\Client\CommandList;

it('describes every command it knows', function () {
    expect(CommandList::lines())->toBe([
        '/help — show this list',
        '/login <username> — set the name peers see',
        '/logout — give the name up and go back to Anonymous',
        '/quit — close the client, like Esc',
    ]);
});

it('takes each name from its constant', function () {
    // The dispatch in ChatClient matches on the same constants, so a renamed
    // command cannot leave the help text behind.
    expect(CommandList::lines()[0])->toStartWith('/' . Command::HELP . ' ');
    expect(CommandList::lines()[1])->toStartWith('/' . Command::LOGIN . ' ');
    expect(CommandList::lines()[2])->toStartWith('/' . Command::LOGOUT . ' ');
    expect(CommandList::lines()[3])->toStartWith('/' . Command::QUIT . ' ');
});

it('shows the usage of a command that takes an argument', function () {
    // The hint is keyed by the same constant as the description, so it cannot
    // go stale on its own.
    expect(CommandList::lines()[1])->toBe('/login <username> — set the name peers see');
});
