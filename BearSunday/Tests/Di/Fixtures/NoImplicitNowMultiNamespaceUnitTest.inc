<?php

namespace Fixtures\Domain\BlockOne;

use DateTime;

class First
{
    // Line 12: ERROR — DateTime imported as global within this block
    public function now(): DateTime
    {
        return new DateTime();
    }
}

namespace Fixtures\Domain\BlockTwo;

class Second
{
    // Line 25: OK — this block has its own scope and no `use DateTime;` import --
    // BlockOne's import must not leak in. Resolves to
    // Fixtures\Domain\BlockTwo\DateTime, not the global class.
    public function now()
    {
        return new DateTime();
    }
}
