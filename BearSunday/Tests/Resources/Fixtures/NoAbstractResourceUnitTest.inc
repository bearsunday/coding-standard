<?php

namespace Fixtures\Resource;

// ERROR: abstract Resource class
abstract class ArticleBaseResource
{
    abstract public function onGet(): static;

    public function helper(): void
    {
    }
}

// ERROR: abstract Resource class with a comment between abstract and class
abstract
/* Comment between abstract and class. */
class CommentedBaseResource
{
}

// OK: concrete Resource class
class ArticleResource
{
    public function onGet(): static
    {
        return $this;
    }
}
