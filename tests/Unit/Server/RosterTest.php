<?php

use PhpCliChat\Server\NameRefused;
use PhpCliChat\Server\Roster;

it('labels an id it does not know as Anonymous', function () {
    expect(new Roster()->label(0))->toBe('Anonymous');
});

it('labels a claimed id with its name, in the case it was given', function () {
    $roster = new Roster();
    $roster->claim(0, 'Alice');

    expect($roster->label(0))->toBe('Alice');
});

it('refuses a second claim on the same id, naming the name it holds', function () {
    // A connection is named once. The message echoes the *stored* name, which
    // has already passed the rules below, rather than the rejected input.
    $roster = new Roster();
    $roster->claim(0, 'alice');

    expect(fn () => $roster->claim(0, 'bob'))
        ->toThrow(NameRefused::class, 'you are already logged in as alice');
});

it('refuses a second claim before it looks at the name', function () {
    // The already-logged-in check comes first, so even an invalid second name
    // gets the "already logged in" answer rather than the rule text.
    $roster = new Roster();
    $roster->claim(0, 'alice');

    expect(fn () => $roster->claim(0, 'José'))
        ->toThrow(NameRefused::class, 'you are already logged in as alice');
});

it('refuses a name already held, whatever its case', function () {
    // Comparing exactly would let anyone shadow a name convincingly with a
    // case flip, which is the reason duplicates are refused at all.
    $roster = new Roster();
    $roster->claim(0, 'alice');

    expect(fn () => $roster->claim(1, 'ALICE'))
        ->toThrow(NameRefused::class, 'the name ALICE is taken');
});

it('allows a name that merely resembles one already held', function () {
    $roster = new Roster();
    $roster->claim(0, 'alice');
    $roster->claim(1, 'Alicia');

    expect($roster->label(1))->toBe('Alicia');
});

it('refuses a name the client renders itself, whatever its case', function (string $name, string $message) {
    // Anonymous and me both break none of the rules above, so without this
    // check /login Anonymous impersonates everyone who has not logged in,
    // and /login me impersonates the reader's own local echo.
    expect(fn () => new Roster()->claim(0, $name))
        ->toThrow(NameRefused::class, $message);
})->with([
    'as written' => ['Anonymous', 'Anonymous is not a name you can take'],
    'lowercased' => ['anonymous', 'anonymous is not a name you can take'],
    'shouted' => ['ANONYMOUS', 'ANONYMOUS is not a name you can take'],
    'the local-echo label' => ['me', 'me is not a name you can take'],
    'the local-echo label, shouted' => ['ME', 'ME is not a name you can take'],
    'the local-echo label, capitalised' => ['Me', 'Me is not a name you can take'],
]);

it('refuses a name that is not letters, digits and spaces', function (string $name) {
    expect(fn () => new Roster()->claim(0, $name))
        ->toThrow(NameRefused::class, 'a name must be 1 to 20 letters, digits or spaces');
})->with([
    // ctype_alnum in the C locale accepts ASCII only, so an accent is out.
    'an accent' => ['José'],
    'a script the C locale cannot classify' => ['さくら'],
    'a hyphen' => ['al-ice'],
    'an underscore' => ['a_b'],
    'a colon, which is the render separator' => ['alice:'],
    'a tab, which survives the client parser' => ["John\tDoe"],
    'one character too long' => [str_repeat('a', 21)],
    // ctype_alnum returns false for an empty string in PHP 8, and a
    // whitespace-only name normalises to an empty one.
    'empty' => [''],
    'spaces only' => ['   '],
]);

it('accepts a name at the length limit', function () {
    $roster = new Roster();
    $roster->claim(0, str_repeat('a', 20));

    expect($roster->label(0))->toBe(str_repeat('a', 20));
});

it('accepts letters, digits and interior spaces', function (string $name) {
    $roster = new Roster();
    $roster->claim(0, $name);

    expect($roster->label(0))->toBe($name);
})->with([
    'letters' => ['alice'],
    'digits' => ['alice2'],
    'digits only' => ['1337'],
    'two words' => ['John Doe'],
]);

it('normalises a name on arrival', function (string $given, string $stored) {
    // What is stored is what will display, so the collision check and the
    // rendered label cannot disagree.
    $roster = new Roster();
    $roster->claim(0, $given);

    expect($roster->label(0))->toBe($stored);
})->with([
    'surrounding spaces' => ['  alice  ', 'alice'],
    'a run of interior spaces' => ['John  Doe', 'John Doe'],
]);

it('refuses a name that only differs by a run of spaces', function () {
    // Without normalising, "John  Doe" would shadow "John Doe" on screen.
    $roster = new Roster();
    $roster->claim(0, 'John Doe');

    expect(fn () => $roster->claim(1, 'John  Doe'))
        ->toThrow(NameRefused::class, 'the name John Doe is taken');
});

it('reports whether a release freed anything', function () {
    // /logout answers "you are not logged in" off this, so it has to tell a
    // name that was given up from one that was never held.
    $roster = new Roster();
    $roster->claim(0, 'alice');

    expect($roster->release(0))->toBeTrue();
    expect($roster->release(0))->toBeFalse();
    expect($roster->release(1))->toBeFalse();
});

it('lets a released id claim a name again', function () {
    // A name is claimed once per holding, not once per connection: giving one
    // up is what makes a second claim legal.
    $roster = new Roster();
    $roster->claim(0, 'alice');
    $roster->release(0);
    $roster->claim(0, 'bob');

    expect($roster->label(0))->toBe('bob');
});

it('frees a name when its id is released', function () {
    $roster = new Roster();
    $roster->claim(0, 'alice');
    $roster->release(0);

    expect($roster->label(0))->toBe('Anonymous');

    $roster->claim(1, 'alice');

    expect($roster->label(1))->toBe('alice');
});

it('ignores a release of an id it does not know', function () {
    // Both paths out of ChatServer's hub release unconditionally, and a
    // connection that never logged in has nothing to unset.
    $roster = new Roster();

    expect(fn () => $roster->release(99))->not->toThrow(Throwable::class);
});
