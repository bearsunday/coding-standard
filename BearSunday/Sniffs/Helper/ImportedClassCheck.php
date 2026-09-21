<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Helper;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

use function strrpos;
use function substr;

use const T_AS;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NS_SEPARATOR;
use const T_OPEN_PARENTHESIS;
use const T_SEMICOLON;
use const T_STRING;
use const T_USE;

/**
 * Detects whether a class is already `use`-imported at the top level of a file.
 *
 * Shared by sniffs that only auto-fix a violation when the suggested
 * replacement class is already resolvable in that file — the fixer rewrites a
 * token in place but never inserts a `use` statement itself.
 *
 * This file intentionally does not end in `Sniff.php`: PHPCS only discovers
 * `Sniffs/<Category>/<Name>Sniff.php` files as sniffs, so this trait is never
 * picked up as one, while the package's PSR-4 autoload still resolves it.
 */
trait ImportedClassCheck
{
    /**
     * True when the file has a top-level `use ...\<className>;` (or `... as <className>`)
     * import. A trait `use` inside a class, or a closure `use (...)` nested in a
     * function, is always inside a scope condition and is skipped by the
     * `conditions` check. A closure `use (...)` at the very top of a file (not
     * nested in any class or function, so `conditions` is also empty) is instead
     * excluded by checking that the name is not immediately followed by `(`.
     */
    private function isClassImported(File $phpcsFile, string $className): bool
    {
        $tokens = $phpcsFile->getTokens();
        $usePtr = 0;

        while (true) {
            $usePtr = $phpcsFile->findNext(T_USE, $usePtr + 1);
            if ($usePtr === false) {
                return false;
            }

            if (! empty($tokens[$usePtr]['conditions'])) {
                continue;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $usePtr + 1, null, true);
            if ($next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
                // A top-level closure `use (...)`, not a namespace import.
                continue;
            }

            $end = $phpcsFile->findNext([T_SEMICOLON], $usePtr + 1);
            if ($end === false) {
                continue;
            }

            // PHP 8's tokenizer emits a single T_NAME_QUALIFIED/T_NAME_FULLY_QUALIFIED
            // token for `Foo\Bar\Baz` rather than separate T_STRING/T_NS_SEPARATOR
            // tokens, so both forms — plus plain unqualified T_STRING — are accepted
            // here. `as <alias>` resets the run so the alias, the name actually
            // usable in this file, wins.
            $importedName = '';

            for ($i = $usePtr + 1; $i < $end; $i++) {
                $code = $tokens[$i]['code'];
                if ($code === T_AS) {
                    $importedName = '';
                    continue;
                }

                if (
                    $code !== T_STRING
                    && $code !== T_NS_SEPARATOR
                    && $code !== T_NAME_QUALIFIED
                    && $code !== T_NAME_FULLY_QUALIFIED
                    && $code !== T_NAME_RELATIVE
                ) {
                    continue;
                }

                $importedName .= $tokens[$i]['content'];
            }

            // Take the segment after the last `\` so a fully-qualified path, a
            // qualified path, and a plain unqualified name are all handled by
            // one rule.
            $lastSeparator = strrpos($importedName, '\\');
            $shortName     = $lastSeparator === false ? $importedName : substr($importedName, $lastSeparator + 1);

            if ($shortName === $className) {
                return true;
            }
        }
    }
}
