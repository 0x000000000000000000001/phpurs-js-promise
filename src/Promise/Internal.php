<?php

class PhpursPromise {
    public $state = 'pending';
    public $value = null;
    public $reason = null;
    public $handlers = [];

    public static $fuel = 200;

    public static function queueTask(callable $task) {
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

    public function resolve($val) {
        if ($this->state !== 'pending') return;
        $this->state = 'fulfilled';
        $this->value = $val;
        foreach ($this->handlers as $handler) {
            self::queueTask(function() use ($handler) {
                ($handler->onFulfilled)($this->value);
            });
        }
        $this->handlers = [];
    }

    public function reject($err) {
        if ($this->state !== 'pending') return;
        $this->state = 'rejected';
        $this->reason = $err;
        foreach ($this->handlers as $handler) {
            self::queueTask(function() use ($handler) {
                ($handler->onRejected)($this->reason);
            });
        }
        $this->handlers = [];
    }

    public function then($onFulfilled, $onRejected = null) {
        $p = new PhpursPromise();
        $handler = (object)[
            'onFulfilled' => function($v) use ($onFulfilled, $p) {
                try {
                    if ($onFulfilled) {
                        $res = $onFulfilled($v);
                        if ($res instanceof PhpursPromise) {
                            $res->then([$p, 'resolve'], [$p, 'reject']);
                        } else {
                            $p->resolve($res);
                        }
                    } else {
                        $p->resolve($v);
                    }
                } catch (\Throwable $e) {
                    $p->reject($e);
                }
            },
            'onRejected' => function($e) use ($onRejected, $p) {
                try {
                    if ($onRejected) {
                        $res = $onRejected($e);
                        if ($res instanceof PhpursPromise) {
                            $res->then([$p, 'resolve'], [$p, 'reject']);
                        } else {
                            $p->resolve($res);
                        }
                    } else {
                        $p->reject($e);
                    }
                } catch (\Throwable $e2) {
                    $p->reject($e2);
                }
            }
        ];
        
        if ($this->state === 'fulfilled') {
            self::queueTask(function() use ($handler) {
                ($handler->onFulfilled)($this->value);
            });
        } elseif ($this->state === 'rejected') {
            self::queueTask(function() use ($handler) {
                ($handler->onRejected)($this->reason);
            });
        } else {
            $this->handlers[] = $handler;
        }
        return $p;
    }
    
    public function catch($onRejected) {
        return $this->then(null, $onRejected);
    }
    
    public function finally($onFinally) {
        return $this->then(
            function($v) use ($onFinally) {
                $onFinally()(); 
                return $v;
            },
            function($e) use ($onFinally) {
                $onFinally()();
                throw $e; 
            }
        );
    }
}

$exports['new'] = function($k) {
    $p = new PhpursPromise();
    $resolve = function($val) use ($p) {
        $p->resolve($val);
    };
    $reject = function($err) use ($p) {
        $p->reject($err);
    };
    $k($resolve, $reject);
    return $p;
};

$exports['then_'] = function($k, $p) {
    return $p->then($k);
};

$exports['thenOrCatch'] = function($k, $c, $p) {
    return $p->then($k, $c);
};

$exports['catch'] = function($c, $p) {
    return $p->catch($c);
};

$exports['finally'] = function($k, $p) {
    return $p->finally($k);
};

$exports['resolve'] = function($a) {
    $p = new PhpursPromise();
    $p->resolve($a);
    return $p;
};

$exports['reject'] = function($err) {
    $p = new PhpursPromise();
    $p->reject($err);
    return $p;
};

$exports['all'] = function($arr) {
    $p = new PhpursPromise();
    if (\count($arr) === 0) {
        $p->resolve([]);
        return $p;
    }
    $results = [];
    $remaining = \count($arr);
    foreach ($arr as $i => $prom) {
        $prom->then(function($v) use (&$results, &$remaining, $i, $p) {
            $results[$i] = $v;
            $remaining--;
            if ($remaining === 0) {
                k\sort($results);
                $p->resolve(array_values($results));
            }
        }, function($e) use ($p) {
            $p->reject($e);
        });
    }
    return $p;
};

$exports['race'] = function($arr) {
    $p = new PhpursPromise();
    foreach ($arr as $prom) {
        $prom->then(function($v) use ($p) {
            $p->resolve($v);
        }, function($e) use ($p) {
            $p->reject($e);
        });
    }
    return $p;
};
return $exports;
