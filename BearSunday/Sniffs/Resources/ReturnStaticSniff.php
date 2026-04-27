<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function preg_match;
use function str_contains;
use function str_replace;

use const T_FUNCTION;

/**
 * Ensures that Resource on* handler methods declare `static` as their return type.
 *
 * BEAR.Sunday ResourceObject methods must return `static` (not `self`, `$this`,
 * `ResourceObject`, or missing) so that the fluent interface type is preserved
 * across inheritance chains.
 *
 * Sniff: BearSunday.Resources.ReturnStatic
 */
final class ReturnStaticSniff implements Sniff
{
    /** @return list<int> */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! str_contains($filePath, '/Resource/')) {
            return;
        }

        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            return;
        }

        if (! preg_match('/^on(Get|Post|Put|Patch|Delete|Head|Options)$/', $methodName)) {
            return;
        }

        // Find return type
        $returnType = $this->getReturnType($phpcsFile, $stackPtr);

        if ($returnType === 'static') {
            return;
        }

        if ($returnType === null) {
            $phpcsFile->addError(
                'Resource handler method "%s" must declare return type "static" (no return type found).',
                $stackPtr,
                'MissingReturnType',
                [$methodName],
            );

            return;
        }

        $phpcsFile->addError(
            'Resource handler method "%s" must declare return type "static", found "%s".',
            $stackPtr,
            'WrongReturnType',
            [$methodName, $returnType],
        );
    }

    private function getReturnType(File $phpcsFile, int $stackPtr): string|null
    {
        $returnType = $phpcsFile->getMethodProperties($stackPtr)['return_type'];

        if ($returnType === '') {
            return null;
        }

        return $returnType;
    }
}
