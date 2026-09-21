<?php

declare(strict_types=1);

namespace BearSunday\Tests;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

use function dirname;
use function reset;

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
     * Runs a sniff (identified by its source file path) against a fixture file
     * and returns the processed PHPCS file, before either inspecting its
     * diagnostics or running its fixer.
     *
     * @param array<string, mixed> $properties
     */
    private function processedFile(string $sniffFile, string $fixtureFile, array $properties): LocalFile
    {
        $config                  = new Config(['--standard=PSR1'], false);
        $ruleset                 = new Ruleset($config);
        $ruleset->sniffs         = [];
        $ruleset->tokenListeners = [];
        $ruleset->registerSniffs([$sniffFile], [], []);
        $ruleset->populateTokenListeners();

        foreach ($ruleset->sniffs as $sniffClass => $_sniff) {
            foreach ($properties as $name => $value) {
                $ruleset->setSniffProperty($sniffClass, $name, [
                    'scope' => 'sniff',
                    'value' => $value,
                ]);
            }
        }

        $file = new LocalFile($fixtureFile, $ruleset, $config);
        $file->process();

        return $file;
    }

    /**
     * Run a sniff (identified by its source file path) against a fixture file.
     *
     * @param array<string, mixed> $properties
     *
     * @return array{errors: array<int, mixed>, warnings: array<int, mixed>}
     */
    protected function processSniff(string $sniffFile, string $fixtureFile, array $properties = []): array
    {
        $file = $this->processedFile($sniffFile, $fixtureFile, $properties);

        return [
            'errors'   => $file->getErrors(),
            'warnings' => $file->getWarnings(),
        ];
    }

    /**
     * Runs a sniff's fixer against a fixture file and returns the resulting
     * source, so a fix's actual output — not just its `fixable` flag — is
     * exercised by tests.
     *
     * @param array<string, mixed> $properties
     */
    protected function fixFile(string $sniffFile, string $fixtureFile, array $properties = []): string
    {
        $file = $this->processedFile($sniffFile, $fixtureFile, $properties);
        $file->fixer->fixFile();

        return $file->fixer->getContents();
    }

    /**
     * Returns the absolute path to a sniff PHP file.
     */
    protected function sniffPath(string $category, string $name): string
    {
        return dirname(__DIR__) . '/Sniffs/' . $category . '/' . $name . '.php';
    }

    /**
     * Returns the first error entry recorded on the given line, regardless of
     * column, or null when the line has no errors. Useful for asserting on
     * error metadata (e.g. `fixable`) without hard-coding column numbers.
     *
     * @param array<int, array<int, list<array<string, mixed>>>> $errors
     *
     * @return array<string, mixed>|null
     */
    protected function firstErrorOnLine(array $errors, int $line): array|null
    {
        if (! isset($errors[$line])) {
            return null;
        }

        $columns = $errors[$line];
        $first   = reset($columns);

        return $first === false ? null : $first[0];
    }
}
