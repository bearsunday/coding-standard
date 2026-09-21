<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Di;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function in_array;
use function ltrim;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strcasecmp;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use const T_AS;
use const T_CLASS;
use const T_COMMA;
use const T_COMMENT;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOC_COMMENT;
use const T_ENUM;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_OPEN_CURLY_BRACKET;
use const T_OPEN_PARENTHESIS;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
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
 * A `new ClassName` expression is resolved against PHP's actual class-name
 * resolution rules (see https://www.php.net/manual/en/language.namespaces.rules.php)
 * before comparing it to DateTime/DateTimeImmutable: a fully-qualified name
 * (`\DateTime`) is absolute; `namespace\DateTime` is relative to the current
 * namespace; a bare or qualified name (`DateTime`, `Sub\DateTime`) is
 * resolved against the file's `use` imports first, falling back to the
 * current namespace. Class names have no fallback to the global namespace --
 * unlike functions and constants -- so an unqualified `new DateTime()` inside
 * `namespace App\Domain;` with no matching `use DateTime;` import does NOT
 * refer to the global class and is correctly left unflagged.
 *
 * One residual, inherent limitation: if the current namespace itself defines
 * (elsewhere in the project) its own class literally named `DateTime` or
 * `DateTimeImmutable` with no `use` import shadowing it, this sniff cannot
 * detect that from a single file's tokens alone -- doing so would require a
 * project-wide symbol index, which is out of scope for a PHPCS sniff.
 *
 * Trigger paths: <code>/Resource/</code>, <code>/Service/</code>, <code>/Domain/</code>
 * Excluded paths: <code>/Module/</code>, <code>/Provider/</code>, <code>/Factory/</code>
 *
 * Sniff: BearSunday.Di.NoImplicitNow
 */
final class NoImplicitNowSniff implements Sniff
{
    private const DATE_CLASSES = ['datetime', 'datetimeimmutable'];

    private const TRIGGER_PATHS = ['/Resource/', '/Service/', '/Domain/'];

    private const EXCLUDE_PATHS = ['/Module/', '/Provider/', '/Factory/'];

    private const COMMENT_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

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

        $tokens = $phpcsFile->getTokens();
        $next   = $phpcsFile->findNext(self::COMMENT_TOKENS, $stackPtr + 1, null, true);
        if ($next === false) {
            return;
        }

        $tokenCode = $tokens[$next]['code'];
        $read      = $this->readName($phpcsFile, $next);
        if ($read === null) {
            return;
        }

        [$rawName, $afterNamePtr] = $read;

        $absoluteName = $this->resolveAbsoluteClassName($phpcsFile, $rawName, $tokenCode);
        if (! in_array(strtolower($absoluteName), self::DATE_CLASSES, true)) {
            return;
        }

        $openParen = $phpcsFile->findNext(self::COMMENT_TOKENS, $afterNamePtr, null, true);
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
            [$rawName],
        );
    }

    /**
     * True when the argument list is empty, or is exactly a single 'now' string
     * literal (case-insensitively -- PHP's date parser treats 'now'/'NOW'/'Now'
     * identically) -- both read the wall clock at call time. Any other single or
     * multi-argument form is an explicit timestamp and is allowed. Comments
     * inside the argument list are ignored, not mistaken for an argument.
     */
    private function isImplicitNow(File $phpcsFile, int $openParen, int $closeParen): bool
    {
        $tokens = $phpcsFile->getTokens();
        $argPtr = $phpcsFile->findNext(self::COMMENT_TOKENS, $openParen + 1, $closeParen, true);
        if ($argPtr === false) {
            return true;
        }

        if ($tokens[$argPtr]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return false;
        }

        if (strcasecmp(trim($tokens[$argPtr]['content'], '\'"'), 'now') !== 0) {
            return false;
        }

        // Nothing else (besides comments) in the argument list after the 'now' literal.
        return $phpcsFile->findNext(self::COMMENT_TOKENS, $argPtr + 1, $closeParen, true) === false;
    }

    /**
     * Resolve a `new` class-name reference to its absolute (leading-backslash-
     * stripped) form per PHP's class-name resolution rules. Class names never
     * fall back to the global namespace: an unqualified or qualified name
     * resolves against the file's `use` imports first, then the current
     * namespace; only a name with no matching import and an empty current
     * namespace stays as literally written (i.e. already global).
     */
    private function resolveAbsoluteClassName(File $phpcsFile, string $rawName, int $tokenCode): string
    {
        if ($tokenCode === T_NAME_FULLY_QUALIFIED) {
            return ltrim($rawName, '\\');
        }

        if ($tokenCode === T_NAME_RELATIVE) {
            $withoutPrefix = (string) preg_replace('/^namespace\\\\/i', '', $rawName);
            $namespace     = $this->collectNamespace($phpcsFile);

            return $namespace === '' ? $withoutPrefix : $namespace . '\\' . $withoutPrefix;
        }

        $firstSeparator = strpos($rawName, '\\');
        $firstSegment   = $firstSeparator === false ? $rawName : substr($rawName, 0, $firstSeparator);
        $remainder      = $firstSeparator === false ? '' : substr($rawName, $firstSeparator);

        $aliased = $this->collectUseAliases($phpcsFile)[strtolower($firstSegment)] ?? null;
        if ($aliased !== null) {
            return $aliased . $remainder;
        }

        $namespace = $this->collectNamespace($phpcsFile);

        return $namespace === '' ? $rawName : $namespace . '\\' . $rawName;
    }

    /**
     * The file's leading `namespace X;` (or bracketed `namespace X { ... }`)
     * declaration, without the leading backslash. Empty string for the global
     * namespace or a file with no declaration. A file with multiple bracketed
     * namespace blocks is treated as having just the first one -- BEAR.Sunday
     * files never use that rare, PSR-discouraged form.
     */
    private function collectNamespace(File $phpcsFile): string
    {
        $tokens = $phpcsFile->getTokens();
        $nsPtr  = $phpcsFile->findNext(T_NAMESPACE, 0);
        if ($nsPtr === false) {
            return '';
        }

        $next = $phpcsFile->findNext(self::COMMENT_TOKENS, $nsPtr + 1, null, true);
        if ($next === false) {
            return '';
        }

        $read = $this->readName($phpcsFile, $next);

        return $read === null ? '' : ltrim($read[0], '\\');
    }

    /**
     * Maps each `use` import's local name (lowercased) to its absolute (no
     * leading backslash) fully qualified name: `use App\Foo;` maps `foo` to
     * `App\Foo`; `use App\Foo as Bar;` maps `bar` to `App\Foo`. Trait `use`
     * statements inside a class/trait/interface/enum body, and `use
     * function`/`use const` imports, are skipped -- they cannot import a
     * class. Grouped imports (`use Ns\{A, B};`) are not supported and are
     * skipped for that statement only.
     *
     * @return array<string, string>
     */
    private function collectUseAliases(File $phpcsFile): array
    {
        $tokens  = $phpcsFile->getTokens();
        $aliases = [];

        $usePtr = 0;
        while (($usePtr = $phpcsFile->findNext(T_USE, $usePtr + 1)) !== false) {
            if ($this->isInsideClassLikeScope($tokens, $usePtr)) {
                continue;
            }

            $ptr = $phpcsFile->findNext(self::COMMENT_TOKENS, $usePtr + 1, null, true);
            if ($ptr === false) {
                continue;
            }

            if ($tokens[$ptr]['code'] === T_FUNCTION || $tokens[$ptr]['code'] === T_CONST) {
                continue;
            }

            $this->parseUseImports($phpcsFile, $ptr, $aliases);
        }

        return $aliases;
    }

    /** @param array<int, array<string, mixed>> $tokens */
    private function isInsideClassLikeScope(array $tokens, int $ptr): bool
    {
        $conditions = $tokens[$ptr]['conditions'] ?? [];

        return in_array(T_CLASS, $conditions, true)
            || in_array(T_TRAIT, $conditions, true)
            || in_array(T_INTERFACE, $conditions, true)
            || in_array(T_ENUM, $conditions, true);
    }

    /** @param array<string, string> $aliases */
    private function parseUseImports(File $phpcsFile, int $ptr, array &$aliases): void
    {
        $tokens = $phpcsFile->getTokens();

        while (true) {
            $read = $this->readName($phpcsFile, $ptr);
            if ($read === null) {
                return;
            }

            [$name, $afterName] = $read;

            $afterNamePtr = $phpcsFile->findNext(self::COMMENT_TOKENS, $afterName, null, true);
            if ($afterNamePtr !== false && $tokens[$afterNamePtr]['code'] === T_OPEN_CURLY_BRACKET) {
                // Grouped import `use Ns\{A, B};` -- not supported, skip the statement.
                return;
            }

            $localName = null;
            $ptr       = $afterName;
            if ($afterNamePtr !== false && $tokens[$afterNamePtr]['code'] === T_AS) {
                $aliasPtr = $phpcsFile->findNext(self::COMMENT_TOKENS, $afterNamePtr + 1, null, true);
                if ($aliasPtr !== false && $tokens[$aliasPtr]['code'] === T_STRING) {
                    $localName = $tokens[$aliasPtr]['content'];
                    $ptr       = $aliasPtr + 1;
                }
            }

            if ($localName === null) {
                $lastSeparator = strrpos($name, '\\');
                $localName     = $lastSeparator === false ? $name : substr($name, $lastSeparator + 1);
            }

            $aliases[strtolower($localName)] = ltrim($name, '\\');

            $next = $phpcsFile->findNext(self::COMMENT_TOKENS, $ptr, null, true);
            if ($next === false || $tokens[$next]['code'] !== T_COMMA) {
                return;
            }

            $ptr = $phpcsFile->findNext(self::COMMENT_TOKENS, $next + 1, null, true);
            if ($ptr === false) {
                return;
            }
        }
    }

    /**
     * Read a (possibly fully-qualified) name starting at $ptr, returning the raw
     * text as written and the pointer just past it. PHP_CodeSniffer 4.x
     * tokenizes `\DateTimeImmutable` / `Sub\DateTime` / `namespace\DateTime` as
     * a single T_NAME_FULLY_QUALIFIED / T_NAME_QUALIFIED / T_NAME_RELATIVE
     * token (mirroring PHP 8's native tokenizer); a bare `DateTime` is a plain
     * T_STRING. Some tokenizer paths still split a leading `\` into a separate
     * T_NS_SEPARATOR, so that legacy form is also handled.
     *
     * @return array{0: string, 1: int}|null
     */
    private function readName(File $phpcsFile, int $ptr): array|null
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$ptr]['code'];

        if (
            $code === T_NAME_FULLY_QUALIFIED
            || $code === T_NAME_QUALIFIED
            || $code === T_NAME_RELATIVE
            || $code === T_STRING
        ) {
            return [$tokens[$ptr]['content'], $ptr + 1];
        }

        if ($code !== T_NS_SEPARATOR) {
            return null;
        }

        $name = '';
        $i    = $ptr;
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
