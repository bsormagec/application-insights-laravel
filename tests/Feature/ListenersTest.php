<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Sormagec\AppInsightsLaravel\Listeners\LogSlowQuery;
use Sormagec\AppInsightsLaravel\Listeners\LogJobFailed;
use Sormagec\AppInsightsLaravel\Listeners\LogMailSent;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mime\Email;

it('logs slow queries', function () {
    config(['appinsights-laravel.db_slow_ms' => 10]);
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $connection = Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted('select * from users', [], 20, $connection);
    app(LogSlowQuery::class)->handle($event);

    expect(count($server->getQueue()))->toBe($initialCount + 1);
    $queue = $server->getQueue();
    $lastItem = end($queue);
    expect($lastItem['data']['baseData']['name'])->toBe('SQL Query');
});

it('skips fast queries', function () {
    config(['appinsights-laravel.db_slow_ms' => 100]);
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $connection = Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted('select * from users', [], 20, $connection);
    app(LogSlowQuery::class)->handle($event);

    expect(count($server->getQueue()))->toBe($initialCount);
});

it('logs failed jobs', function () {
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('resolveName')->andReturn('TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('payload')->andReturn(['id' => 1]);

    $event = new JobFailed('mysql', $job, new \Exception('Job Failed'));
    app(LogJobFailed::class)->handle($event);

    expect(count($server->getQueue()))->toBe($initialCount + 1);
});

it('logs sent messages', function () {
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $email = Mockery::mock(Email::class);
    $email->shouldReceive('getSubject')->andReturn('Test Subject');
    $email->shouldReceive('getTo')->andReturn(['to@example.com']);

    $sentMessage = Mockery::mock(SentMessage::class);
    $sentMessage->shouldReceive('getOriginalMessage')->andReturn($email);

    $event = new MessageSent($sentMessage, []);
    app(LogMailSent::class)->handle($event);

    expect(count($server->getQueue()))->toBe($initialCount + 1);
    $queue = $server->getQueue();
    $lastItem = end($queue);
    expect($lastItem['data']['baseData']['name'])->toBe('mail.sent');
});
