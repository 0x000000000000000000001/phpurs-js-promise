<?php

$exports['fromError'] = function($a) {
    return $a;
};

$exports['_toError'] = function($just, $nothing, $ref) {
    if ($ref instanceof \Exception || $ref instanceof \Throwable) {
        return $just($ref);
    }
    return $nothing;
};

return $exports;
