<?php

namespace Fixtures\Resource;

use Koriym\HttpConstants\ResponseHeader;

class ArticleResource
{
    // Line 12: ERROR — string literal for a header that has a real constant
    public function onPost(): static
    {
        $this->headers['Location'] = '/articles/1';

        return $this;
    }

    // Line 20: OK — using the framework constant
    public function onPut(): static
    {
        $this->headers[ResponseHeader::LOCATION] = '/articles/1';

        return $this;
    }

    // Line 28: OK — application-specific header has no ResponseHeader constant
    public function onPatch(): static
    {
        $this->headers['X-Request-Id'] = '123';

        return $this;
    }
}
