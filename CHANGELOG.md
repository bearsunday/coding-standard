# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-11-11

### Changed
- The Doctrine rules that conflict with BEAR.Sunday conventions no longer apply: the `*Interface`, `*Exception` and `Abstract*` naming rules, assignment alignment and the ban on variables in double-quoted strings. A project can drop its own excludes for them
- A native type is no longer required on properties under `/Resource/`, where `ResourceObject`'s untyped `$body`, `$code`, `$headers`, `$view` and `$uri` cannot take one
- Docblock annotations are grouped the BEAR.Sunday way: `@param` with its `@psalm-` and `@phpstan-` variants, `@return` likewise, `@Inject, @Named`, then `@throws`. A project can drop its own `annotationsGroups`

## [0.1.0] - 2026-11-11

First release.

### Added
- The `BearSunday` PHPCS standard: Doctrine Coding Standard plus BEAR.Sunday rules. Install it with `composer require --dev bear/coding-standard` and reference it as `<rule ref="BearSunday"/>`
- Resource rules: `ReturnStatic`, `NoSuperglobals`, `NoAbstractResource`, `NoFinalResource`, `StatusCodeConstant` and `HeaderConstant`
- `Di.NoNewService` reports `new` of any class it does not recognize in `/Resource/`, `/Service/` and `/Domain/`. A value object it reports goes into `allowedClasses` in the project's `phpcs.xml`
- `DbQuery.RedundantType` and `AppMeta.NoAppDirWritablePath`
- `var_dump`, `print_r`, `dd`, `dump` and `error_log` are forbidden
- The BEAR.Sunday rules skip `*/tests/*`; tests are checked by the Doctrine rules and the debug-function ban
