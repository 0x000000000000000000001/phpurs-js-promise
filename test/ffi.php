<?php
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
$exports = [];
$exports = require dirname(__DIR__) . '/src/Promise/Internal.php';
$checks = 0;
function same($expected, $actual)
{
    global $checks;
    if ($expected !== $actual) {
        throw new Exception('Unexpected promise result');
    }
    $checks++;
}
$events = [];
$pending = new PhpursPromise();
$pending->then(function ($value) use (&$events) {
    $events[] = $value;
}, null);
same([], $events);
$pending->resolve(41);
same([41], $events);
$pending->resolve(99);
$pending->reject(new Exception('late'));
same([41], $events);
same(42, $pending->then(fn($value) => $value + 1, null)->value);
same(41, $pending->then(null, null)->value);
$error = new Exception('failure');
$rejected = $exports['reject']($error);
same($error, $rejected->then(null, null)->reason);
same(42, $rejected->catch(fn($reason) => 42)->value);
same(
    $error,
    $pending->then(function () use ($error) {
        throw $error;
    }, null)->reason,
);
same(43, $pending->then(fn() => $exports['resolve'](43), null)->value);
same($error, $pending->then(fn() => $exports['reject']($error), null)->reason);
same(44, $rejected->catch(fn() => $exports['resolve'](44))->value);
same($error, $rejected->catch(fn() => $exports['reject']($error))->reason);
$finallyCount = 0;
same(
    41,
    $pending->finally(function () use (&$finallyCount) {
        $finallyCount++;
    })->value,
);
same(
    $error,
    $rejected->finally(function () use (&$finallyCount) {
        $finallyCount++;
    })->reason,
);
same(2, $finallyCount);
same(41, $pending->finally(fn() => $exports['resolve'](1))->value);
same($error, $rejected->finally(fn() => $exports['resolve'](1))->reason);
$first = new PhpursPromise();
$second = new PhpursPromise();
$all = $exports['all']([$first, $second]);
$second->resolve(2);
same('pending', $all->state);
$first->resolve(1);
same([1, 2], $all->value);
same([], $exports['all']([])->value);
same(2, $exports['race']([$second, $first])->value);
PhpursPromise::$fuel = 0;
$events = [];
PhpursPromise::queueTask(function () use (&$events) {
    $events[] = 'queued';
});
if (class_exists('Revolt\\EventLoop')) {
    same([], $events);
    Revolt\EventLoop::run();
}
same(['queued'], $events);
echo "$checks Promise checks passed\n";
