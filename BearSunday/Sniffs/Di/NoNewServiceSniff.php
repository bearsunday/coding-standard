<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Di;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function in_array;
use function ltrim;
use function str_contains;
use function str_ends_with;
use function str_replace;

use const T_CLOSE_CURLY_BRACKET;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_OPEN_CURLY_BRACKET;
use const T_PARENT;
use const T_SELF;
use const T_SEMICOLON;
use const T_STATIC;
use const T_STRING;
use const T_THROW;
use const T_WHITESPACE;

/**
 * Forbids `new ClassName` inside service-layer classes.
 *
 * In BEAR.Sunday, object instantiation of services is the responsibility of
 * Ray.Di modules (the composition root). Resource, Service, and Domain classes
 * receive their dependencies via constructor injection. Direct `new` bypasses
 * the DI container and breaks AOP interception.
 *
 * Allowed exceptions:
 *   - Value objects / DTOs: class names ending in Input, Dto, Entity, ValueObject, Exception
 *   - Date/time classes: DateTime, DateTimeImmutable, DateTimeInterface, DateInterval, DateTimeZone
 *   - SPL classes listed in ALLOWED_SPL_CLASSES
 *   - throw new ... expressions (domain exceptions must be thrown, not injected)
 *   - Configurable via $allowedSuffixes property
 *
 * Trigger paths: <code>/Resource/</code>, <code>/Service/</code>, <code>/Domain/</code>
 * Excluded paths: <code>/Module/</code>, <code>/Provider/</code>, <code>/Factory/</code>
 * Exclusions are evaluated before trigger paths.
 *
 * Sniff: BearSunday.Di.NoNewService
 */
final class NoNewServiceSniff implements Sniff
{
    /**
     * Additional class name suffixes to allow.
     * Configure in ruleset.xml:
     *   <property name="allowedSuffixes" type="array">
     *     <element value="Request"/>
     *   </property>
     *
     * @var list<string>
     */
    public array $allowedSuffixes = [];

    private const array ALLOWED_SUFFIXES = [
        'Input',
        'Dto',
        'Entity',
        'ValueObject',
        'Exception',
    ];

    private const array ALLOWED_DATE_CLASSES = [
        'DateInterval',
        'DateTime',
        'DateTimeImmutable',
        'DateTimeInterface',
        'DateTimeZone',
    ];

    private const array ALLOWED_SPL_CLASSES = [
        'SplDoublyLinkedList',
        'SplFileInfo',
        'SplFileObject',
        'SplFixedArray',
        'SplHeap',
        'SplMaxHeap',
        'SplMinHeap',
        'SplObjectStorage',
        'SplObserver',
        'SplPriorityQueue',
        'SplQueue',
        'SplStack',
        'SplSubject',
        'SplTempFileObject',
    ];

    private const array TRIGGER_PATHS = [
        '/Resource/',
        '/Service/',
        '/Domain/',
    ];

    private const array EXCLUDE_PATHS = [
        '/Module/',
        '/Provider/',
        '/Factory/',
    ];

    /** @return list<int> */
    public function register(): array
    {
        return [T_NEW];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());

        // Check excluded paths first
        foreach (self::EXCLUDE_PATHS as $excluded) {
            if (str_contains($filePath, $excluded)) {
                return;
            }
        }

        // Check trigger paths
        $triggered = false;
        foreach (self::TRIGGER_PATHS as $trigger) {
            if (str_contains($filePath, $trigger)) {
                $triggered = true;
                break;
            }
        }

        if (! $triggered) {
            return;
        }

        // Check if this is `throw new ...`
        if ($this->isThrowContext($phpcsFile, $stackPtr)) {
            return;
        }

        // Get the class name being instantiated
        $className = $this->getClassName($phpcsFile, $stackPtr);
        if ($className === null) {
            return;
        }

        // Strip leading backslash for comparison
        $bareClass = ltrim($className, '\\');

        if ($this->isAllowed($bareClass)) {
            return;
        }

        $phpcsFile->addError(
            'Direct instantiation of "%s" is not allowed in service-layer classes. '
            . 'Use Ray.Di dependency injection instead. '
            . 'Value objects (Input, Dto, Entity, ValueObject, Exception) and date classes are exempt.',
            $stackPtr,
            'NewServiceFound',
            [$className],
        );
    }

    private function isAllowed(string $bareClass): bool
    {
        if (in_array($bareClass, self::ALLOWED_DATE_CLASSES, true)) {
            return true;
        }

        if (in_array($bareClass, self::ALLOWED_SPL_CLASSES, true)) {
            return true;
        }

        // Built-in allowed suffixes
        foreach (self::ALLOWED_SUFFIXES as $suffix) {
            if (str_ends_with($bareClass, $suffix)) {
                return true;
            }
        }

        // Configurable additional suffixes
        foreach ($this->allowedSuffixes as $suffix) {
            if (str_ends_with($bareClass, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isThrowContext(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $i = $stackPtr - 1;
        while ($i >= 0) {
            $code = $tokens[$i]['code'];
            if ($code === T_THROW) {
                return true;
            }

            if (in_array($code, [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET], true)) {
                return false;
            }

            $i--;
        }

        return false;
    }

    private function getClassName(File $phpcsFile, int $stackPtr): string|null
    {
        $tokens = $phpcsFile->getTokens();

        $next = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($next === false) {
            return null;
        }

        // Handle static/self/parent keywords
        $code = $tokens[$next]['code'];
        if (in_array($code, [T_STATIC, T_SELF, T_PARENT], true)) {
            return $tokens[$next]['content'];
        }

        // Collect fully qualified class name
        $name = '';
        $i    = $next;
        while ($i < $phpcsFile->numTokens) {
            $code = $tokens[$i]['code'];
            if ($code !== T_STRING && $code !== T_NS_SEPARATOR) {
                break;
            }

            $name .= $tokens[$i]['content'];

            $i++;
        }

        return $name !== '' ? $name : null;
    }
}
