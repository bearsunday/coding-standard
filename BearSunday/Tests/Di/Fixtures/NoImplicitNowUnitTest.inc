<?php

namespace Fixtures\Domain;

use DateTime;
use DateTimeImmutable;

class Enable
{
    public string $status;
    public DateTimeInterface $datePublished;
    public DateTimeInterface $dateExpired;

    // Lines 17, 18: ERROR — implicit "now" via DateTimeImmutable (imported above)
    public function isValid(): bool
    {
        return $this->status === 'valid' && $this->datePublished < new DateTimeImmutable()
            && $this->dateExpired > new DateTimeImmutable();
    }
}

class ValidRichTemplate
{
    // Lines 28, 29: OK — explicit timestamp argument, not "now"
    // Line 31: ERROR (x2) — implicit "now" via DateTime (imported above)
    public function isValid(string $status, string $datePublished, string|null $dateExpired): bool
    {
        $datePublishedObj = new DateTime($datePublished);
        $dateExpiredObj   = new DateTime($dateExpired ?? '2999-12-31 23:59:59');

        return $status === 'valid' && $datePublishedObj < new DateTime() && $dateExpiredObj > new DateTime();
    }
}

class AbstractArticleCollection
{
    // Line 40: ERROR — fully-qualified DateTimeImmutable, zero arguments
    public function endOfDay(): DateTimeInterface
    {
        return new \DateTimeImmutable();
    }

    // Line 46: ERROR — 'now' literal is the same as zero arguments
    public function windowEnd(): DateTimeInterface
    {
        return (new \DateTimeImmutable('now'))->modify('+1 day');
    }

    // Line 52: OK — fully-qualified DateTime, explicit non-'now' argument
    public function fixedPoint(): DateTimeInterface
    {
        return new \DateTime('2024-01-01');
    }

    // Line 58: ERROR — class name and 'now' literal are matched case-insensitively
    public function shoutedNow(): DateTimeInterface
    {
        return new \DATETIMEIMMUTABLE('NOW');
    }

    // Line 64: ERROR — a comment before the closing paren is not an argument
    public function commentedEmpty(): DateTimeInterface
    {
        return new DateTimeImmutable(/* current moment */);
    }

    // Line 70: ERROR — a comment after the 'now' literal does not add a real argument
    public function commentedNow(): DateTimeInterface
    {
        return new DateTime('now' /* explicit but still now */);
    }

    // Line 76: ERROR — a comment between the class name and the opening paren
    public function commentedBeforeParen(): DateTimeInterface
    {
        return new DateTimeImmutable /* current moment */ ();
    }
}
