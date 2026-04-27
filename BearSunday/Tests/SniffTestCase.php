<?php

declare(strict_types=1);

namespace BearSunday\Tests;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Base class for PHPCS sniff unit tests.
 *
 * Provides a helper that runs a single sniff file against a fixture file
 * and returns the collected errors and warnings, keyed by line number.
 *
 * We load PSR1 only to prevent Config from auto-discovering the local phpcs.xml,
 * then clear its listeners so each test runs only the sniff under test.
 */
abstract class SniffTestCase extends TestCase
{
    /**
     * Run a sniff (identified by its source file path) against a fixture file.
     *
     * @return array{errors: array<int, mixed>, warnings: array<int, mixed>}
     */
    protected function processSniff(string $sniffFile, string $fixtureFile): array
    {
        $config                  = new Config(['--standard=PSR1'], false);
        $ruleset                 = new Ruleset($config);
        $ruleset->sniffs         = [];
        $ruleset->tokenListeners = [];
        $ruleset->registerSniffs([$sniffFile], [], []);
        $ruleset->populateTokenListeners();

        $file = new LocalFile($fixtureFile, $ruleset, $config);
        $file->process();

        return [
            'errors'   => $file->getErrors(),
            'warnings' => $file->getWarnings(),
        ];
    }

    /**
     * Returns the absolute path to a sniff PHP file.
     */
    protected function sniffPath(string $category, string $name): string
    {
        return dirname(__DIR__) . '/Sniffs/' . $category . '/' . $name . '.php';
    }
}
