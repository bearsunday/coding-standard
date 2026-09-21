<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Di;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function in_array;
use function ltrim;
use function str_contains;
use function str_replace;
use function trim;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_OPEN_PARENTHESIS;
use const T_STRING;
use const T_WHITESPACE;

/**
 * Forbids reading the wall clock via new DateTime()/DateTimeImmutable() with
 * no arguments, or with a literal 'now' argument.
 *
 * In BEAR.Sunday, the current time is injected (e.g. a DateTimeInterface
 * parameter bound via ray/identity-value-module's IdentityValueModule) so
 * that resource, service, and domain logic stays deterministic and testable
 * at a fixed point in time. `new DateTime()` and `new DateTime('now')` both
 * read the system clock directly and bypass that injection.
 *
 * `new DateTime($string)` / `new DateTimeImmutable($string)` with any other
 * explicit argument is unaffected -- only the "current moment" forms are
 * flagged.
 *
 * Trigger paths: <code>/Resource/</code>, <code>/Service/</code>, <code>/Domain/</code>
 * Excluded paths: <code>/Module/</code>, <code>/Provider/</code>, <code>/Factory/</code>
 *
 * Sniff: BearSunday.Di.NoImplicitNow
 */
final class NoImplicitNowSniff implements Sniff
{
    private const DATE_CLASSES = ['DateTime', 'DateTimeImmutable'];

    private const TRIGGER_PATHS = ['/Resource/', '/Service/', '/Domain/'];

    private const EXCLUDE_PATHS = ['/Module/', '/Provider/', '/Factory/'];

    /** @return list<int> */
    public function register(): array
    {
        return [T_NEW];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! $this->isTriggerPath($filePath)) {
            return;
        }

        $tokens   = $phpcsFile->getTokens();
        $resolved = $this->resolveClassName($phpcsFile, $stackPtr);
        if ($resolved === null) {
            return;
        }

        [$className, $afterNamePtr] = $resolved;
        $className                  = ltrim($className, '\\');
        if (! in_array($className, self::DATE_CLASSES, true)) {
            return;
        }

        $openParen = $phpcsFile->findNext(T_WHITESPACE, $afterNamePtr, null, true);
        if ($openParen === false || $tokens[$openParen]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $closeParen = $tokens[$openParen]['parenthesis_closer'] ?? null;
        if ($closeParen === null) {
            return;
        }

        if (! $this->isImplicitNow($phpcsFile, $openParen, $closeParen)) {
            return;
        }

        $phpcsFile->addError(
            'new %s() reads the wall clock directly. Inject the current time '
            . '(e.g. a DateTimeInterface parameter bound via ray/identity-value-module) '
            . 'instead of hardcoding "now".',
            $stackPtr,
            'ImplicitNow',
            [$className],
        );
    }

    /**
     * True when the argument list is empty, or is exactly a single 'now' string
     * literal -- both read the wall clock at call time. Any other single or
     * multi-argument form is an explicit timestamp and is allowed.
     */
    private function isImplicitNow(File $phpcsFile, int $openParen, int $closeParen): bool
    {
        $tokens = $phpcsFile->getTokens();
        $argPtr = $phpcsFile->findNext(T_WHITESPACE, $openParen + 1, $closeParen, true);
        if ($argPtr === false) {
            return true;
        }

        if ($tokens[$argPtr]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return false;
        }

        if (trim($tokens[$argPtr]['content'], '\'"') !== 'now') {
            return false;
        }

        // Nothing else in the argument list after the 'now' literal.
        return $phpcsFile->findNext(T_WHITESPACE, $argPtr + 1, $closeParen, true) === false;
    }

    /**
     * Resolve the (possibly fully-qualified) class name following `new`, returning
     * the name and the token pointer just past it.
     *
     * PHP_CodeSniffer 4.x tokenizes `\DateTimeImmutable` / `Sub\DateTime` as a
     * single T_NAME_FULLY_QUALIFIED / T_NAME_QUALIFIED token (mirroring PHP 8's
     * native tokenizer); a bare `DateTime` is a plain T_STRING. Some tokenizer
     * paths still split a leading `\` into a separate T_NS_SEPARATOR, so that
     * legacy form is also handled.
     *
     * @return array{0: string, 1: int}|null
     */
    private function resolveClassName(File $phpcsFile, int $stackPtr): array|null
    {
        $tokens = $phpcsFile->getTokens();
        $next   = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($next === false) {
            return null;
        }

        $code = $tokens[$next]['code'];
        if (
            $code === T_NAME_FULLY_QUALIFIED
            || $code === T_NAME_QUALIFIED
            || $code === T_NAME_RELATIVE
            || $code === T_STRING
        ) {
            return [$tokens[$next]['content'], $next + 1];
        }

        if ($code !== T_NS_SEPARATOR) {
            return null;
        }

        $name = '';
        $i    = $next;
        while ($i < $phpcsFile->numTokens) {
            $tokenCode = $tokens[$i]['code'];
            if ($tokenCode !== T_STRING && $tokenCode !== T_NS_SEPARATOR) {
                break;
            }

            $name .= $tokens[$i]['content'];
            $i++;
        }

        return $name !== '' ? [$name, $i] : null;
    }

    private function isTriggerPath(string $filePath): bool
    {
        foreach (self::EXCLUDE_PATHS as $excluded) {
            if (str_contains($filePath, $excluded)) {
                return false;
            }
        }

        foreach (self::TRIGGER_PATHS as $trigger) {
            if (str_contains($filePath, $trigger)) {
                return true;
            }
        }

        return false;
    }
}
