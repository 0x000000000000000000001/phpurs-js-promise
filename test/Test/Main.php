<?php

return [
    'delay' => function($ms) {
        return function() use ($ms) {
            $p = new \Promise\Internal\PhpursPromise();
            if (\class_exists('\\Revolt\\EventLoop')) {
                \Revolt\EventLoop::delay($ms / 1000, function() use ($p) {
                    $p->resolve(0);
                });
            } else {
                \usleep($ms * 1000);
                $p->resolve(0);
            }
            return $p;
        };
    },
    'failAfter' => function($ms) {
        return function() use ($ms) {
            $p = new \Promise\Internal\PhpursPromise();
            if (\class_exists('\\Revolt\\EventLoop')) {
                \Revolt\EventLoop::delay($ms / 1000, function() use ($p) {
                    $p->reject(new \Exception("fail"));
                });
            } else {
                \usleep($ms * 1000);
                $p->reject(new \Exception("fail"));
            }
            return $p;
        };
    }
];
