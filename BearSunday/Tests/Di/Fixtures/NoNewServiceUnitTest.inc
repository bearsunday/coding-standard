<?php

namespace Fixtures\Service;

use DateTimeImmutable;

class ArticleService
{
    // OK: throw new Exception
    public function validate(string $slug): void
    {
        if ($slug === '') {
            throw new \InvalidArgumentException('slug must not be empty');
        }
    }

    // OK: value object DTO
    public function createInput(string $slug): ArticleInput
    {
        return new ArticleInput($slug);
    }

    // OK: DateTimeImmutable allowed
    public function getTimestamp(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    // ERROR: injecting a service via new
    public function badMethod(): OtherService
    {
        return new OtherService();
    }

    // ERROR: another service
    public function anotherBad(): ArticleRepository
    {
        return new ArticleRepository();
    }

    // OK: throw expression in ternary-like expression
    public function throwExpression(string $slug): void
    {
        $slug !== '' ?: throw new OtherService();
    }

    // OK: throw expression in match
    public function matchThrow(string $status): void
    {
        match ($status) {
            'ok' => true,
            default => throw new OtherService(),
        };
    }

    // OK: throw expression in arrow function
    public function arrowThrow(): callable
    {
        return static fn () => throw new OtherService();
    }
}
