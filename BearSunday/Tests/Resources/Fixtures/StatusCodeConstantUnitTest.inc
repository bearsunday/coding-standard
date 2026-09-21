<?php

namespace Fixtures\Resource;

use Koriym\HttpConstants\StatusCode;

class ArticleResource
{
    // Line 12: ERROR — magic number for a code that has a real constant
    public function onGet(int $id): static
    {
        $this->code = 404;

        return $this;
    }

    // Line 20: OK — using the framework constant
    public function onPost(): static
    {
        $this->code = StatusCode::CREATED;

        return $this;
    }

    // Line 28: OK — 422 has no StatusCode constant in this package version;
    // flagging it would tell the user to write undefined code.
    public function onPut(): static
    {
        $this->code = 422;

        return $this;
    }
}
