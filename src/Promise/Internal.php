<?php

class PhpursPromise
{
    public $state = 'pending';
    public $value;
    public $reason;
    public $handlers = [];

    // Keep short chains synchronous; yield through Revolt after this many tasks.
    public static $fuel = 200;

    public static function queueTask(callable $task)
    {
        if (self::$fuel <= 0) {
            self::$fuel = 200;
            if (class_exists('\\Revolt\\EventLoop')) {
                \Revolt\EventLoop::queue($task);
            } else {
                $task();
            }
        } else {
            self::$fuel--;
            $task();
        }
    }

    public function resolve($value)
    {
        if ($this->state !== 'pending') {
            return;
        }
        $this->state = 'fulfilled';
        $this->value = $value;
        foreach ($this->handlers as $handler) {
            self::queueTask(function () use ($handler) {
                ($handler->onFulfilled)($this->value);
            });
        }
        $this->handlers = [];
    }

    public function reject($error)
    {
        if ($this->state !== 'pending') {
            return;
        }
        $this->state = 'rejected';
        $this->reason = $error;
        foreach ($this->handlers as $handler) {
            self::queueTask(function () use ($handler) {
                ($handler->onRejected)($this->reason);
            });
        }
        $this->handlers = [];
    }

    public function then($onFulfilled, $onRejected)
    {
        $nextPromise = new PhpursPromise();
        $handler = (object) [
            'onFulfilled' => self::chainHandler($onFulfilled, $nextPromise, false),
            'onRejected' => self::chainHandler($onRejected, $nextPromise, true),
        ];

        if ($this->state === 'fulfilled') {
            self::queueTask(function () use ($handler) {
                ($handler->onFulfilled)($this->value);
            });
        } elseif ($this->state === 'rejected') {
            self::queueTask(function () use ($handler) {
                ($handler->onRejected)($this->reason);
            });
        } else {
            $this->handlers[] = $handler;
        }
        return $nextPromise;
    }

    private static function chainHandler($callback, $nextPromise, $isRejection)
    {
        return function ($value) use ($callback, $nextPromise, $isRejection) {
            try {
                if (!$callback) {
                    if ($isRejection) {
                        $nextPromise->reject($value);
                    } else {
                        $nextPromise->resolve($value);
                    }
                    return;
                }
                $result = $callback($value);
                if ($result instanceof PhpursPromise) {
                    $result->then([$nextPromise, 'resolve'], [$nextPromise, 'reject']);
                } else {
                    $nextPromise->resolve($result);
                }
            } catch (\Throwable $error) {
                $nextPromise->reject($error);
            }
        };
    }

    public function catch($onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function finally($onFinally)
    {
        return $this->then(
            function ($value) use ($onFinally) {
                $result = $onFinally();
                if ($result instanceof PhpursPromise) {
                    return $result->then(function () use ($value) {
                        return $value;
                    }, null);
                }
                return $value;
            },
            function ($error) use ($onFinally) {
                $result = $onFinally();
                if ($result instanceof PhpursPromise) {
                    return $result->then(function () use ($error) {
                        throw $error;
                    }, null);
                }
                throw $error;
            },
        );
    }
}

$exports['new'] = function ($k) {
    $promise = new PhpursPromise();
    $resolve = function ($value) use ($promise) {
        $promise->resolve($value);
    };
    $reject = function ($error) use ($promise) {
        $promise->reject($error);
    };
    try {
        $k($resolve, $reject);
    } catch (\Throwable $error) {
        $promise->reject($error);
    }
    return $promise;
};

$exports['then_'] = function ($k, $promise) {
    return $promise->then($k, null);
};

$exports['thenOrCatch'] = function ($k, $c, $promise) {
    return $promise->then($k, $c);
};

$exports['catch'] = function ($c, $promise) {
    return $promise->catch($c);
};

$exports['finally'] = function ($k, $promise) {
    return $promise->finally($k);
};

$exports['resolve'] = function ($a) {
    $promise = new PhpursPromise();
    $promise->resolve($a);
    return $promise;
};

$exports['reject'] = function ($error) {
    $promise = new PhpursPromise();
    $promise->reject($error);
    return $promise;
};

$exports['all'] = function ($promises) {
    $promise = new PhpursPromise();
    if (\count($promises) === 0) {
        $promise->resolve([]);
        return $promise;
    }
    $results = [];
    $remaining = \count($promises);
    foreach ($promises as $index => $inputPromise) {
        $inputPromise->then(
            function ($value) use (&$results, &$remaining, $index, $promise) {
                $results[$index] = $value;
                $remaining--;
                if ($remaining === 0) {
                    \ksort($results);
                    $promise->resolve(array_values($results));
                }
            },
            function ($error) use ($promise) {
                $promise->reject($error);
            },
        );
    }
    return $promise;
};

$exports['race'] = function ($promises) {
    $promise = new PhpursPromise();
    foreach ($promises as $inputPromise) {
        $inputPromise->then(
            function ($value) use ($promise) {
                $promise->resolve($value);
            },
            function ($error) use ($promise) {
                $promise->reject($error);
            },
        );
    }
    return $promise;
};
return $exports;
