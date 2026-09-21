<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function in_array;
use function ltrim;
use function str_contains;
use function str_replace;
use function strtoupper;
use function trim;

use const T_CLOSE_SQUARE_BRACKET;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_OBJECT_OPERATOR;
use const T_OPEN_SQUARE_BRACKET;
use const T_VARIABLE;

/**
 * Requires header name constants instead of string literals for $this->headers keys.
 *
 * The header list mirrors Koriym\HttpConstants\ResponseHeader exactly. Names
 * without a matching constant there (e.g. Content-Range, Link, Pragma) are
 * intentionally not flagged — telling a user to write an undefined constant
 * would be worse than saying nothing.
 *
 * Sniff: BearSunday.Resources.HeaderConstant
 */
final class HeaderConstantSniff implements Sniff
{
    /**
     * Fully qualified class name used in the suggested replacement. The fixer
     * always emits an absolute reference (leading `\`), so it never depends on
     * a `use` import being present; the inherited
     * SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly rule adds the
     * `use` statement and shortens the reference on the next phpcbf pass.
     *
     * Configure in ruleset.xml:
     *   <property name="headerClass" value="App\Http\ResponseHeader"/>
     */
    public string $headerClass = 'Koriym\HttpConstants\ResponseHeader';

    /**
     * Header names to keep as string literals.
     *
     * Configure in ruleset.xml:
     *   <property name="allowedHeaders" type="array">
     *     <element value="Location"/>
     *   </property>
     *
     * @var list<string>
     */
    public array $allowedHeaders = [];

    private const KNOWN_HEADERS = [
        'Access-Control-Allow-Origin',
        'Accept-Patch',
        'Accept-Ranges',
        'Age',
        'Allow',
        'Cache-Control',
        'Connection',
        'Content-Disposition',
        'Content-Encoding',
        'Content-Language',
        'Content-Length',
        'Content-Location',
        'Content-MD5',
        'Content-Security-Policy',
        'Content-Type',
        'Date',
        'ETag',
        'Expires',
        'Last-Modified',
        'Location',
        'P3P',
        'Proxy-Authenticate',
        'Public-Key-Pins',
        'Refresh',
        'Retry-After',
        'Server',
        'Set-Cookie',
        'Status',
        'Strict-Transport-Security',
        'Trailer',
        'Transfer-Encoding',
        'Upgrade',
        'Vary',
        'Warning',
        'WWW-Authenticate',
        'X-Content-Duration',
        'X-Content-Type-Options',
        'X-Powered-By',
        'X-UA-Compatible',
        'X-XSS-Protection',
    ];

    /** @return list<int> */
    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if (! str_contains($phpcsFile->getFilename(), '/Resource/')) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if ($tokens[$stackPtr]['content'] !== '$this') {
            return;
        }

        $keyPtr = $this->headerKeyLiteral($phpcsFile, $stackPtr);
        if ($keyPtr === null) {
            return;
        }

        $header = trim($tokens[$keyPtr]['content'], '\'"');
        if (in_array($header, $this->allowedHeaders, true)) {
            return;
        }

        $constant = $this->constantFor($header);
        if ($constant === null) {
            return;
        }

        $configuredClass = $this->headerClass;
        $fqcn            = '\\' . ltrim($configuredClass, '\\');

        $fix = $phpcsFile->addFixableError(
            'Header name "%s" must be written as %s::%s, not a string literal.',
            $keyPtr,
            'StringHeaderName',
            [$header, $configuredClass, $constant],
        );

        if (! $fix) {
            return;
        }

        $phpcsFile->fixer->replaceToken($keyPtr, $fqcn . '::' . $constant);
    }

    /**
     * Returns the pointer to the string literal used as a $this->headers key, if any.
     */
    private function headerKeyLiteral(File $phpcsFile, int $stackPtr): int|null
    {
        $tokens = $phpcsFile->getTokens();

        $operator = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
        if ($operator === false || $tokens[$operator]['code'] !== T_OBJECT_OPERATOR) {
            return null;
        }

        $property = $phpcsFile->findNext(Tokens::$emptyTokens, $operator + 1, null, true);
        if ($property === false || $tokens[$property]['content'] !== 'headers') {
            return null;
        }

        $open = $phpcsFile->findNext(Tokens::$emptyTokens, $property + 1, null, true);
        if ($open === false || $tokens[$open]['code'] !== T_OPEN_SQUARE_BRACKET) {
            return null;
        }

        $key = $phpcsFile->findNext(Tokens::$emptyTokens, $open + 1, null, true);
        if ($key === false || $tokens[$key]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $close = $phpcsFile->findNext(Tokens::$emptyTokens, $key + 1, null, true);
        if ($close === false || $tokens[$close]['code'] !== T_CLOSE_SQUARE_BRACKET) {
            return null;
        }

        return $key;
    }

    private function constantFor(string $header): string|null
    {
        foreach (self::KNOWN_HEADERS as $known) {
            if (strtoupper($header) !== strtoupper($known)) {
                continue;
            }

            return strtoupper(str_replace('-', '_', $known));
        }

        return null;
    }
}
