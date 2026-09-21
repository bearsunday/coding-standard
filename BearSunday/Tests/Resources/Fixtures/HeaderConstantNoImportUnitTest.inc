<?php

namespace Fixtures\Resource;

class ArticleResourceNoImport
{
    // Line 10: ERROR — no `use Koriym\HttpConstants\ResponseHeader;` in this file
    public function onGet(int $id): static
    {
        $this->headers['Location'] = '/articles/1';

        return $this;
    }
}
