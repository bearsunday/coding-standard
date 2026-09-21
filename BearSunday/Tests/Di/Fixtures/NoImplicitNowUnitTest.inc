<?php

namespace Fixtures\Domain;

class Enable
{
    public string $status;
    public DateTimeInterface $datePublished;
    public DateTimeInterface $dateExpired;

    // Lines 14, 15: ERROR — implicit "now" via DateTimeImmutable
    public function isValid(): bool
    {
        return $this->status === 'valid' && $this->datePublished < new DateTimeImmutable()
            && $this->dateExpired > new DateTimeImmutable();
    }
}

class ValidRichTemplate
{
    // Lines 25, 26: OK — explicit timestamp argument, not "now"
    // Line 28: ERROR (x2) — implicit "now" via DateTime
    public function isValid(string $status, string $datePublished, string|null $dateExpired): bool
    {
        $datePublishedObj = new DateTime($datePublished);
        $dateExpiredObj   = new DateTime($dateExpired ?? '2999-12-31 23:59:59');

        return $status === 'valid' && $datePublishedObj < new DateTime() && $dateExpiredObj > new DateTime();
    }
}

class AbstractArticleCollection
{
    // Line 37: ERROR — fully-qualified DateTimeImmutable, zero arguments
    public function endOfDay(): DateTimeInterface
    {
        return new \DateTimeImmutable();
    }

    // Line 43: ERROR — 'now' literal is the same as zero arguments
    public function windowEnd(): DateTimeInterface
    {
        return (new \DateTimeImmutable('now'))->modify('+1 day');
    }

    // Line 49: OK — fully-qualified DateTime, explicit non-'now' argument
    public function fixedPoint(): DateTimeInterface
    {
        return new \DateTime('2024-01-01');
    }
}
