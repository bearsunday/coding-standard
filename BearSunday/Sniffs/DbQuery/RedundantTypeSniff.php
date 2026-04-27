<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\DbQuery;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function count;
use function explode;
use function in_array;
use function ltrim;
use function str_starts_with;
use function strtolower;
use function trim;

use const T_ABSTRACT;
use const T_ATTRIBUTE;
use const T_COLON;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOC_COMMENT;
use const T_DOUBLE_ARROW;
use const T_FINAL;
use const T_FUNCTION;
use const T_OPEN_PARENTHESIS;
use const T_PARAM_NAME;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_STATIC;
use const T_WHITESPACE;

/**
 * Warns when a #[DbQuery] attribute includes a redundant `type:` argument.
 *
 * Ray.MediaQuery's DbQueryInterceptor infers the fetch mode from the method's
 * return type:
 *   - nullable class / class|null  →  getRow()   (type: 'row' is redundant)
 *   - array                        →  getRowList() (type: 'row_list' is redundant)
 *
 * The type: argument adds noise without information. Remove it and let the
 * return type declaration be the single source of truth.
 *
 * Sniff: BearSunday.DbQuery.RedundantType
 *
 * @internal Best-effort attribute argument parser; complex compound types may
 *   be missed. TODO v0.2: handle generic array<T> and intersection types.
 */
final class RedundantTypeSniff implements Sniff
{
    private const array BUILTIN_TYPES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'resource',
        'string',
        'true',
        'void',
    ];

    /** @return list<int> */
    public function register(): array
    {
        return [T_ATTRIBUTE];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Find the attribute closer
        $attrCloser = $tokens[$stackPtr]['attribute_closer'] ?? null;
        if ($attrCloser === null) {
            return;
        }

        // Check this is a DbQuery attribute
        $namePtr = $phpcsFile->findNext([T_WHITESPACE], $stackPtr + 1, $attrCloser, true);
        if ($namePtr === false) {
            return;
        }

        $attrName = '';
        $i        = $namePtr;
        while ($i < $attrCloser && $tokens[$i]['code'] !== T_OPEN_PARENTHESIS) {
            $attrName .= $tokens[$i]['content'];
            $i++;
        }

        $attrName = trim($attrName);
        if ($attrName !== 'DbQuery') {
            return;
        }

        // Parse attribute arguments looking for type: 'row' or type: 'row_list'
        $typeValue = $this->findTypeArgument($phpcsFile, $stackPtr, $attrCloser);
        if ($typeValue === null) {
            return;
        }

        // Now find the function this attribute belongs to.
        $funcPtr = $this->findAttributedFunction($phpcsFile, $attrCloser);
        if ($funcPtr === null) {
            return;
        }

        $returnType = $this->getReturnType($phpcsFile, $funcPtr);
        if ($returnType === null) {
            return;
        }

        $isRedundant = false;
        if ($typeValue === 'row' && $this->isNullableClass($returnType)) {
            $isRedundant = true;
        } elseif ($typeValue === 'row_list' && $returnType === 'array') {
            $isRedundant = true;
        }

        if (! $isRedundant) {
            return;
        }

        $phpcsFile->addWarning(
            'The type: \'%s\' argument in #[DbQuery] is redundant; '
            . 'the return type "%s" already implies this fetch mode.',
            $stackPtr,
            'RedundantType',
            [$typeValue, $returnType],
        );
    }

    private function findTypeArgument(File $phpcsFile, int $attrPtr, int $attrCloser): string|null
    {
        $tokens = $phpcsFile->getTokens();
        $i      = $attrPtr;

        while ($i < $attrCloser) {
            // Look for T_PARAM_NAME (PHP 8.0+ named argument)
            if ($tokens[$i]['code'] === T_PARAM_NAME && $tokens[$i]['content'] === 'type') {
                // Skip colon and whitespace
                $valuePtr = $phpcsFile->findNext([T_COLON, T_WHITESPACE], $i + 1, $attrCloser, true);
                if ($valuePtr !== false && $tokens[$valuePtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = trim($tokens[$valuePtr]['content'], "'\"");
                    if ($value === 'row' || $value === 'row_list') {
                        return $value;
                    }
                }
            }

            // Also handle string literals 'type' as array key pattern
            if (
                $tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING
                && trim($tokens[$i]['content'], "'\"") === 'type'
            ) {
                $valuePtr = $phpcsFile->findNext([T_WHITESPACE, T_DOUBLE_ARROW], $i + 1, $attrCloser, true);
                if ($valuePtr !== false && $tokens[$valuePtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = trim($tokens[$valuePtr]['content'], "'\"");
                    if ($value === 'row' || $value === 'row_list') {
                        return $value;
                    }
                }
            }

            $i++;
        }

        return null;
    }

    private function findAttributedFunction(File $phpcsFile, int $attrCloser): int|null
    {
        $tokens = $phpcsFile->getTokens();
        $i      = $attrCloser + 1;

        while ($i < $phpcsFile->numTokens) {
            $code = $tokens[$i]['code'];
            if (
                in_array(
                    $code,
                    [
                        T_ABSTRACT,
                        T_COMMENT,
                        T_DOC_COMMENT,
                        T_FINAL,
                        T_PRIVATE,
                        T_PROTECTED,
                        T_PUBLIC,
                        T_STATIC,
                        T_WHITESPACE,
                    ],
                    true,
                )
            ) {
                $i++;
                continue;
            }

            if ($code === T_ATTRIBUTE) {
                $nextAttrCloser = $tokens[$i]['attribute_closer'] ?? null;
                if ($nextAttrCloser === null) {
                    return null;
                }

                $i = $nextAttrCloser + 1;
                continue;
            }

            return $code === T_FUNCTION ? $i : null;
        }

        return null;
    }

    private function getReturnType(File $phpcsFile, int $stackPtr): string|null
    {
        $returnType = $phpcsFile->getMethodProperties($stackPtr)['return_type'];

        if ($returnType === '') {
            return null;
        }

        return $returnType;
    }

    private function isNullableClass(string $returnType): bool
    {
        $nullable     = str_starts_with($returnType, '?');
        $nonNullTypes = [];
        $types        = explode('|', ltrim($returnType, '?'));
        foreach ($types as $type) {
            if (strtolower($type) === 'null') {
                $nullable = true;
                continue;
            }

            if ($type === '') {
                continue;
            }

            $nonNullTypes[] = $type;
        }

        if (! $nullable || count($nonNullTypes) !== 1) {
            return false;
        }

        return ! $this->isBuiltinType($nonNullTypes[0]);
    }

    private function isBuiltinType(string $type): bool
    {
        return in_array(strtolower(ltrim($type, '\\')), self::BUILTIN_TYPES, true);
    }
}
