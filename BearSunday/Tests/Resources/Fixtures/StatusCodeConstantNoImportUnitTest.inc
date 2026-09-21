<?php

namespace Fixtures\Resource;

class ArticleResource
{
    // Line 10: ERROR, not auto-fixable — StatusCode is not imported in this file
    public function onGet(int $id): static
    {
        $this->code = 404;

        return $this;
    }
}
