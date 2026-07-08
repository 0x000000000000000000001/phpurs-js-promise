<?php

$exports['new'] = function($k) {
    $result = (object)[
        'state' => 'pending',
        'value' => null,
        'reason' => null
    ];
    $resolve = function($val) use ($result) {
        $result->state = 'fulfilled';
        $result->value = $val;
    };
    $reject = function($err) use ($result) {
        $result->state = 'rejected';
        $result->reason = $err;
    };
    
    // k is an EffectFn2 in PureScript, so it's a PHP callable taking 2 args.
    // It returns an Effect (which in PHP evaluates to the returned value or just runs).
    $k($resolve, $reject);
    
    return $result;
};

$exports['then_'] = function($k, $p) {
    if ($p->state === 'fulfilled') {
        // k is EffectFn1, meaning a PHP callable taking 1 arg.
        return $k($p->value);
    } elseif ($p->state === 'rejected') {
        return $p;
    }
    return $p;
};

$exports['thenOrCatch'] = function($k, $c, $p) {
    if ($p->state === 'fulfilled') {
        return $k($p->value);
    } elseif ($p->state === 'rejected') {
        return $c($p->reason);
    }
    return $p;
};

$exports['catch'] = function($c, $p) {
    if ($p->state === 'rejected') {
        return $c($p->reason);
    }
    return $p;
};

$exports['finally'] = function($k, $p) {
    $k()(); // k is Effect (Promise Unit) so we evaluate the outer Effect thunk
    return $p;
};

$exports['resolve'] = function($a) {
    return (object)[
        'state' => 'fulfilled',
        'value' => $a,
        'reason' => null
    ];
};

$exports['reject'] = function($err) {
    return (object)[
        'state' => 'rejected',
        'value' => null,
        'reason' => $err
    ];
};

$exports['all'] = function($arr) {
    $results = [];
    foreach ($arr as $p) {
        if ($p->state === 'rejected') {
            return $p;
        }
        // Assuming pure synchronous resolution, pending shouldn't happen,
        // but if it does, it's a logic error we can't await in PHP anyway.
        $results[] = $p->value;
    }
    return (object)[
        'state' => 'fulfilled',
        'value' => $results,
        'reason' => null
    ];
};

$exports['race'] = function($arr) {
    if (count($arr) > 0) {
        return $arr[0];
    }
    return (object)[
        'state' => 'pending',
        'value' => null,
        'reason' => null
    ];
};
