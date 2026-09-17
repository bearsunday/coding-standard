<?php

declare(strict_types=1);

namespace BearSunday\Tests\AppMeta;

use BearSunday\Tests\SniffTestCase;

use function dirname;
use function reset;

final class NoAppDirWritablePathSniffTest extends SniffTestCase
{
    public function testSniffDetectsVarTmpPathsDerivedFromAppDir(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('AppMeta', 'NoAppDirWritablePathSniff'),
            dirname(__DIR__) . '/AppMeta/Fixtures/NoAppDirWritablePathUnitTest.inc',
        );
        $errors = $result['errors'];

        // $this->appMeta->appDir . '/var/tmp/cache/qiq' (line 13)
        $this->assertArrayHasKey(13, $errors, 'Expected error on line 13 (appDir . /var/tmp path)');
        $this->assertStringContainsString(
            "\$appMeta->tmpDir . '/cache/qiq'",
            $this->firstMessage($errors[13]),
            'Diagnostic must recommend tmpDir plus the suffix after /var/tmp',
        );

        // $this->appMeta->appDir . '/var/tmp/cache' (line 14)
        $this->assertArrayHasKey(14, $errors, 'Expected error on line 14 (appDir . /var/tmp path)');
        $this->assertStringContainsString(
            "\$appMeta->tmpDir . '/cache'",
            $this->firstMessage($errors[14]),
            'Diagnostic must recommend tmpDir plus the suffix after /var/tmp',
        );

        // $this->appMeta->appDir . '/var/tmp' (line 21) → bare prefix, suffix === ''
        $this->assertArrayHasKey(21, $errors, 'Expected error on line 21 (bare appDir . /var/tmp)');
        $this->assertStringContainsString(
            'use $appMeta->tmpDir instead',
            $this->firstMessage($errors[21]),
            'Diagnostic must recommend plain tmpDir with no suffix appended',
        );

        // $meta->appDir . '/var/tmp/cache' (line 22) → detected regardless of the
        // AbstractAppMeta binding's variable name, not just $appMeta/$this->appMeta
        $this->assertArrayHasKey(22, $errors, 'Expected error on line 22 ($meta->appDir, non-appMeta binding name)');

        // $this->cache->appDir . '/var/tmp/cache' (line 23) → accepted false positive:
        // the sniff does not verify the receiver is actually an AbstractAppMeta,
        // trading this rare case for not missing real occurrences under other
        // binding names (see line 22)
        $this->assertArrayHasKey(23, $errors, 'Expected error on line 23 (accepted false positive: unrelated appDir)');

        // $this->appMeta->appDir . ('/var/tmp/cache') (line 24) → parenthesized
        // literal; T_OPEN_PARENTHESIS must not hide the string from findNext()
        $this->assertArrayHasKey(24, $errors, 'Expected error on line 24 (parenthesized /var/tmp literal)');
    }

    public function testSniffAllowsNonTmpAppDirConcatenation(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('AppMeta', 'NoAppDirWritablePathSniff'),
            dirname(__DIR__) . '/AppMeta/Fixtures/NoAppDirWritablePathUnitTest.inc',
        );
        $errors = $result['errors'];

        // $this->appMeta->appDir . '/var/log/app.log' (line 15) → only /var/tmp is the anti-pattern
        $this->assertArrayNotHasKey(15, $errors, 'Should not error on appDir . /var/log path');
        // $this->appMeta->appDir . '/var/assets' (line 16) → assets/config paths are valid
        $this->assertArrayNotHasKey(16, $errors, 'Should not error on appDir . assets path');
        // $this->appMeta->tmpDir . '/cache/qiq' (line 18) → correct usage
        $this->assertArrayNotHasKey(18, $errors, 'Should not error when using $appMeta->tmpDir');
        // $this->appMeta->appDir . '/var/build' (line 19) → compiled artifact, shipped read-only
        $this->assertArrayNotHasKey(19, $errors, 'Should not error on appDir . build path');
        // $this->appMeta->appDir . '/var/tmp-old' (line 20) → not the /var/tmp segment, must not match by prefix
        $this->assertArrayNotHasKey(20, $errors, 'Should not error on appDir . /var/tmp-old path');
    }

    /** @param array<int, array<int, string>> $lineErrors */
    private function firstMessage(array $lineErrors): string
    {
        $columnErrors = reset($lineErrors);

        return (string) reset($columnErrors)['message'];
    }
}
