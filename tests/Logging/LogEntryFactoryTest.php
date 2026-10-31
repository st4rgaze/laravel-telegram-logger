<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Monolog\Level;
use St4rgaze\TelegramLogger\Logging\ExceptionSummary;
use St4rgaze\TelegramLogger\Logging\LogEntryFactory;

function queryException(): QueryException
{
    return new QueryException(
        'mysql',
        'select * from users where email = ?',
        ['budi@example.com'],
        new PDOException('SQLSTATE[HY000]: General error'),
    );
}

it('copies the record details', function (): void {
    config(['app.name' => 'Popout', 'app.timezone' => 'Asia/Jakarta']);

    $entry = app(LogEntryFactory::class)->make(
        logRecord(Level::Warning, 'Payment failed', ['order' => 42], ['tag' => 'x']),
        channelConfig(),
        14,
    );

    expect($entry)
        ->level->toBe(Level::Warning)
        ->channel->toBe('telegram')
        ->message->toBe('Payment failed')
        ->context->toBe(['order' => 42])
        ->extra->toBe(['tag' => 'x'])
        ->exceptions->toBe([])
        ->request->toBe([])
        ->suppressedCount->toBe(14)
        ->appName->toBe('Popout')
        ->environment->toBe('testing')
        ->and($entry->datetime->format('Y-m-d H:i:s T'))->toBe('2026-11-01 10:22:03 WIB');
});

it('summarises the exception chain and removes it from the context', function (): void {
    $exception = new RuntimeException('Card declined', 0, new LogicException('Gateway timeout'));

    $entry = app(LogEntryFactory::class)->make(logRecord(context: ['exception' => $exception, 'order' => 42]), channelConfig(), 0);

    expect($entry->context)->toBe(['order' => 42])
        ->and($entry->exceptions)->toHaveCount(2)
        ->and($entry->exceptions[0])->toBeInstanceOf(ExceptionSummary::class)
        ->and($entry->exceptions[0]->class)->toBe(RuntimeException::class)
        ->and($entry->exceptions[0]->message)->toBe('Card declined')
        ->and($entry->exceptions[0]->file)->toBe(str_replace(base_path().'/', '', __FILE__))
        ->and($entry->exceptions[0]->line)->toBe($exception->getLine())
        ->and($entry->exceptions[0]->trace)->toEndWith('{main}')
        ->and($entry->exceptions[1]->class)->toBe(LogicException::class);
});

it('keeps absolute paths outside the base path', function (): void {
    expect(ExceptionSummary::chain(new RuntimeException('x'), '/somewhere/else')[0]->file)->toBe(__FILE__);
});

it('redacts the context and extra before the formatter sees them', function (): void {
    $entry = app(LogEntryFactory::class)->make(
        logRecord(context: ['password' => 'hunter2', 'customer' => ['email' => 'budi@example.com']], extra: ['Authorization' => 'Bearer x']),
        channelConfig(),
        0,
    );

    expect($entry->context)->toBe(['password' => '[redacted]', 'customer' => ['email' => '[redacted]']])
        ->and($entry->extra)->toBe(['Authorization' => '[redacted]']);
});

it('masks patterns in the message, exception messages and traces', function (): void {
    ini_set('zend.exception_ignore_args', '0');
    $exception = (fn (string $email): RuntimeException => new RuntimeException("No user {$email}"))('a@bc.io');

    $entry = app(LogEntryFactory::class)->make(
        logRecord(message: 'Login failed for budi@example.com', context: ['exception' => $exception]),
        channelConfig(),
        0,
    );

    expect($entry->message)->toBe('Login failed for [redacted]')
        ->and($entry->exceptions[0]->message)->toBe('No user [redacted]')
        ->and($entry->exceptions[0]->trace)->not->toContain('a@bc.io');
});

it('describes a query exception with placeholder sql', function (): void {
    $exception = queryException();

    $entry = app(LogEntryFactory::class)->make(
        logRecord(message: $exception->getMessage(), context: ['exception' => $exception]),
        channelConfig(['redact' => ['patterns' => []]]),
        0,
    );

    expect($entry->exceptions[0]->message)->toContain('email = ?')->not->toContain('budi@example.com')
        ->and($entry->message)->toContain('email = ?')->not->toContain('budi@example.com');
});

it('survives context that cannot be serialised', function (): void {
    $self = new stdClass;
    $self->self = $self;

    $deep = [];

    for ($i = 0; $i < 20; $i++) {
        $deep = ['level' => $deep];
    }

    $entry = app(LogEntryFactory::class)->make(logRecord(context: [
        'resource' => fopen('php://memory', 'r'),
        'closure' => fn (): int => 1,
        'self' => $self,
        'deep' => $deep,
    ]), channelConfig(), 0);

    expect($entry->context)->toHaveKeys(['resource', 'closure', 'self', 'deep']);
});

it('never puts call arguments in the trace', function (): void {
    ini_set('zend.exception_ignore_args', '0');
    $login = fn (string $user, string $password): RuntimeException => new RuntimeException('Login failed');
    $exception = $login('budi', 'hunter2pass');

    $entry = app(LogEntryFactory::class)->make(logRecord(context: ['exception' => $exception]), channelConfig(), 0);

    expect($exception->getTraceAsString())->toContain('hunter2pass')
        ->and($entry->exceptions[0]->trace)->not->toContain('hunter2pass')->not->toContain('budi')
        ->toContain('{closure')->toMatch('/#\d+ \{main\}$/');
});

it('redacts request values', function (): void {
    Illuminate\Support\Facades\Route::post('/notify', fn (): array => [
        'route' => app(LogEntryFactory::class)->make(logRecord(), channelConfig(), 0)->request['route'] ?? null,
    ])->name('notify:budi@example.com');

    expect($this->post('/notify')->json('route'))->toBe('notify:[redacted]');
});

it('masks patterns in the request path', function (): void {
    Illuminate\Support\Facades\Route::post('/users/{email}', fn (): array => app(LogEntryFactory::class)->make(logRecord(), channelConfig(), 0)->request);

    expect($this->post('/users/budi@example.com')->json('url'))->toBe('http://localhost/users/[redacted]');
});
