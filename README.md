# phpurs-js-promise

> PHPurs checkout: see the [PHP library development guide](https://github.com/0x000000000000000000001/phpurs/blob/main/docs/libraries.md) for the TAST toolchain, FFI conventions and validation commands. Run `./bin/test` from this package with the PHP toolchain prepared.

[![Latest release](http://img.shields.io/github/release/purescript-contrib/purescript-js-promise.svg)](https://github.com/purescript-web/purescript-js-promise/releases)
[![Build status](https://github.com/purescript-contrib/purescript-js-promise/workflows/CI/badge.svg?branch=master)](https://github.com/purescript-web/purescript-js-promise/actions?query=workflow%3ACI+branch%3Amaster)
[![Pursuit](https://pursuit.purescript.org/packages/purescript-js-promise/badge)](https://pursuit.purescript.org/packages/purescript-js-promise)

Types and low-level implementations for JavaScript Promises.


## PHP runtime development

`src/Promise/Internal.php` contains `PhpursPromise` and the exported FFI functions. `chainHandler` handles both fulfilled and rejected continuations, including returned promises and callback exceptions. `src/Promise/Rejection.php` converts rejection values to PHP throwables when possible.

Short callback chains execute immediately. The shared task budget yields through Revolt when it is available; without Revolt, callbacks continue synchronously. Preserve that scheduling contract when changing the runtime. The compiler embeds these FFI files in generated modules, so runtime helpers stay in their source file.

Run `php test/ffi.php` for direct checks, including promise-returning finalizers, or `bin/test` for the full PureScript-to-PHP suite. To also exercise the Revolt scheduling path in a sibling checkout, run:

```sh
php -d auto_prepend_file=../phpurs-aff/vendor/autoload.php test/ffi.php
```

## Installation

```
spago install js-promise
```

## Documentation

Module documentation is [published on Pursuit](http://pursuit.purescript.org/packages/purescript-js-promise).

