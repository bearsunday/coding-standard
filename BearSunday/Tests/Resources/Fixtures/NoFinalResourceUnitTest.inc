<?php

namespace Fixtures\Resource;

// ERROR: final Resource class
final class ArticleResource
{
    public function onGet(): static
    {
        return $this;
    }
}

// OK: non-final Resource class
class PageResource
{
    public function onGet(): static
    {
        return $this;
    }
}

// ERROR: final class with a comment between final and class
final
/* Comment between final and class. */
class CommentedResource
{
}

// ERROR: final readonly Resource class
final readonly class ReadonlyResource
{
}

// OK: final method inside a Resource class
class MethodResource
{
    final public function helper(): void
    {
    }
}

// OK: final constant inside a Resource class
class ConstantResource
{
    final public const FOO = 'bar';
}
