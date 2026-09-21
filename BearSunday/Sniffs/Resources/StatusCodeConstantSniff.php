<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use BearSunday\Sniffs\Helper\ImportedClassCheck;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function array_key_exists;
use function str_contains;

use const T_EQUAL;
use const T_LNUMBER;
use const T_OBJECT_OPERATOR;
use const T_SEMICOLON;
use const T_VARIABLE;

/**
 * Requires HTTP status constants instead of numeric literals for $this->code.
 *
 * The constant table mirrors Koriym\HttpConstants\StatusCode exactly (including
 * its non-obvious names REQUEST_TIME_OUT and GATEWAY_TIME_OUT). Codes without a
 * matching constant there (e.g. 422, 429) are intentionally not flagged — telling
 * a user to write an undefined constant would be worse than saying nothing.
 *
 * Sniff: BearSunday.Resources.StatusCodeConstant
 */
final class StatusCodeConstantSniff implements Sniff
{
    use ImportedClassCheck;

    /**
     * Class name used in the suggested replacement.
     *
     * Configure in ruleset.xml:
     *   <property name="statusCodeClass" value="StatusCode"/>
     */
    public string $statusCodeClass = 'StatusCode';

    private const CONSTANTS = [
        100 => 'CONTINUE_',
        101 => 'SWITCHING_PROTOCOLS',
        102 => 'PROCESSING',
        200 => 'OK',
        201 => 'CREATED',
        202 => 'ACCEPTED',
        203 => 'NON_AUTHORITATIVE_INFORMATION',
        204 => 'NO_CONTENT',
        205 => 'RESET_CONTENT',
        206 => 'PARTIAL_CONTENT',
        300 => 'MULTIPLE_CHOICES',
        301 => 'MOVED_PERMANENTLY',
        302 => 'FOUND',
        303 => 'SEE_OTHER',
        304 => 'NOT_MODIFIED',
        305 => 'USE_PROXY',
        307 => 'TEMPORARY_REDIRECT',
        308 => 'PERMANENT_REDIRECT',
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHORIZED',
        402 => 'PAYMENT_REQUIRED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        406 => 'NOT_ACCEPTABLE',
        407 => 'PROXY_AUTHENTICATION_REQUIRED',
        408 => 'REQUEST_TIME_OUT',
        409 => 'CONFLICT',
        410 => 'GONE',
        411 => 'LENGTH_REQUIRED',
        412 => 'PRECONDITION_FAILED',
        413 => 'REQUEST_ENTITY_TOO_LARGE',
        414 => 'REQUEST_URI_TOO_LARGE',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        416 => 'REQUESTED_RANGE_NOT_SATISFIABLE',
        417 => 'EXPECTATION_FAILED',
        418 => 'IM_A_TEAPOT',
        500 => 'INTERNAL_SERVER_ERROR',
        501 => 'NOT_IMPLEMENTED',
        502 => 'BAD_GATEWAY',
        503 => 'SERVICE_UNAVAILABLE',
        504 => 'GATEWAY_TIME_OUT',
        505 => 'HTTP_VERSION_NOT_SUPPORTED',
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

        $literalPtr = $this->literalAssignedToCode($phpcsFile, $stackPtr);
        if ($literalPtr === null) {
            return;
        }

        $code = (int) $tokens[$literalPtr]['content'];
        if (! array_key_exists($code, self::CONSTANTS)) {
            return;
        }

        $isImported = $this->isClassImported($phpcsFile, $this->statusCodeClass);

        if (! $isImported) {
            $phpcsFile->addError(
                'HTTP status code %d must be written as %s::%s, not a numeric literal. '
                . 'Add "use Koriym\HttpConstants\%s;" to auto-fix this with phpcbf.',
                $literalPtr,
                'MagicStatusCode',
                [$code, $this->statusCodeClass, self::CONSTANTS[$code], $this->statusCodeClass],
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'HTTP status code %d must be written as %s::%s, not a numeric literal.',
            $literalPtr,
            'MagicStatusCode',
            [$code, $this->statusCodeClass, self::CONSTANTS[$code]],
        );

        if (! $fix) {
            return;
        }

        $phpcsFile->fixer->replaceToken($literalPtr, $this->statusCodeClass . '::' . self::CONSTANTS[$code]);
    }

    /**
     * Returns the pointer to the numeric literal assigned to $this->code, if any.
     */
    private function literalAssignedToCode(File $phpcsFile, int $stackPtr): int|null
    {
        $tokens = $phpcsFile->getTokens();

        $operator = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
        if ($operator === false || $tokens[$operator]['code'] !== T_OBJECT_OPERATOR) {
            return null;
        }

        $property = $phpcsFile->findNext(Tokens::$emptyTokens, $operator + 1, null, true);
        if ($property === false || $tokens[$property]['content'] !== 'code') {
            return null;
        }

        $assign = $phpcsFile->findNext(Tokens::$emptyTokens, $property + 1, null, true);
        if ($assign === false || $tokens[$assign]['code'] !== T_EQUAL) {
            return null;
        }

        $value = $phpcsFile->findNext(Tokens::$emptyTokens, $assign + 1, null, true);
        if ($value === false || $tokens[$value]['code'] !== T_LNUMBER) {
            return null;
        }

        // Reject compound expressions such as `$this->code = 200 + $offset;`
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $value + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_SEMICOLON) {
            return null;
        }

        return $value;
    }
}
