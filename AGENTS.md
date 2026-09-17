# Repository Guidelines

## Project Structure & Module Organization

This repository is a PHP_CodeSniffer standard for BEAR.Sunday projects. The published standard lives in `BearSunday/ruleset.xml`. Custom sniffs are under `BearSunday/Sniffs/<Category>/<Name>Sniff.php`, for example `BearSunday/Sniffs/Resources/ReturnStaticSniff.php`. Tests mirror that layout under `BearSunday/Tests/<Category>/<Name>SniffTest.php`, with `.inc` fixture files in each category's `Fixtures/` directory. Shared test helpers are in `BearSunday/Tests/SniffTestCase.php`. Root config files include `composer.json`, `phpcs.xml`, `phpunit.xml`, and `tests-bootstrap.php`.

New sniff checklist: Sniff class + SniffTest + `Fixtures/<Name>UnitTest.inc` + registration in `BearSunday/ruleset.xml` + a matching `### BearSunday.<Category>.<Name>` section in `README.md` (Trigger/Rule/example, following the existing sections' format).

## Build, Test, and Development Commands

- `composer install`: install PHP_CodeSniffer, Doctrine/Slevomat standards, PHPUnit, and the `dealerdirect/phpcodesniffer-composer-installer` plugin that registers `BearSunday` as a resolvable `--standard` name.
- `composer test` or `vendor/bin/phpunit`: run all sniff unit tests from `BearSunday/Tests`.
- `composer phpcs` or `vendor/bin/phpcs`: run the repository self-check using `phpcs.xml`. Run with no path arguments from the repo root so every file under `phpcs.xml`'s scope is checked, not just changed files — scoping to individual files can pass while the full tree still fails.
- `vendor/bin/phpcs --standard=BearSunday path/to/src`: try the packaged standard against another project after dependencies are installed.

Do not commit `vendor/` or PHPUnit cache files.

## Coding Style & Naming Conventions

Use PHP `^8.2` (per `composer.json`) with `declare(strict_types=1);`. Follow the self-check in `phpcs.xml`, which extends Doctrine Coding Standard (Slevomat rules arrive transitively through Doctrine, not selected separately) with two Slevomat sniffs excluded. Keep sniffs `final`, place them in the `BearSunday\Sniffs\<Category>` namespace, and name them `<RuleName>Sniff`. Test classes should be `final` and named `<RuleName>SniffTest` in the matching `BearSunday\Tests\<Category>` namespace. Prefer explicit imports for global functions when the surrounding files do so.

`PHP_CodeSniffer\Sniffs\Sniff::process()` types `$stackPtr` as `int` in PHPCS 4 — match it with a native type hint. After any dependency update, verify against the installed interface (`vendor/squizlabs/php_codesniffer/src/Sniffs/Sniff.php`) rather than assuming the signature.

Class constants remain untyped for PHP 8.2 compatibility (typed class constants require PHP 8.3); `phpcs.xml` excludes `SlevomatCodingStandard.TypeHints.ClassConstantTypeHint` for this specific reason. Do not extend that exclude, or suppress other rules generically, to paper over other PHP-version mismatches without checking each one individually.

## Testing Guidelines

Sniff tests use PHPUnit and the PHPCS APIs directly. Add or update a fixture in `BearSunday/Tests/<Category>/Fixtures/` and assert expected error or warning line numbers in the matching test. When a sniff is path-sensitive, create a temporary path in the test that matches the trigger, such as `/Resource/`. Run `composer test` before submitting changes; run `composer phpcs` for style changes or new PHP files.

## Commit & Pull Request Guidelines

The current history is minimal, so keep commits concise and descriptive, for example `Add DI no-new-service sniff` or `Fix redundant DbQuery type warnings`. Pull requests should explain the rule behavior, list affected sniff codes, include fixture coverage for new diagnostics, and note whether findings are errors, warnings, or fixable warnings. Link related issues when available and include command results for `composer test` and `composer phpcs`.

After opening a PR, request a CodeRabbit review and address verified findings before merging.
