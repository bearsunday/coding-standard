<?php

namespace Fixtures\Resource;

use BEAR\Resource\ResourceObject;

class ArticleResource extends ResourceObject
{
    // Correct: returns static
    public function onGet(int $id): static
    {
        return $this;
    }

    // ERROR: returns self
    public function onPost(string $slug): self
    {
        return $this;
    }

    // ERROR: missing return type
    public function onPut(int $id)
    {
        return $this;
    }

    // ERROR: wrong return type
    public function onDelete(int $id): ResourceObject
    {
        return $this;
    }

    // OK: not an on* handler, no check
    public function helper(): void
    {
    }

    // OK: onHead returns static
    public function onHead(): static
    {
        return $this;
    }
}
