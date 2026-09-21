<?php

namespace Fixtures\Resource;

class ArticleResourceNoImport
{
    // Line 10: ERROR — no `use Koriym\HttpConstants\StatusCode;` in this file
    public function onGet(int $id): static
    {
        $this->code = 404;

        return $this;
    }
}
