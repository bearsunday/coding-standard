<?php

namespace Fixtures\Resource;

class ArticleResource
{
    // Line 10: ERROR, not auto-fixable — ResponseHeader is not imported in this file
    public function onPost(): static
    {
        $this->headers['Location'] = '/articles/1';

        return $this;
    }
}
