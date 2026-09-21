<?php

namespace Fixtures\Domain;

use DateTime as Clock;
use Fixtures\Other\DateTime;

class AliasedToGlobal
{
    // Line 13: ERROR — 'Clock' is a use-alias for the global DateTime class
    public function now(): Clock
    {
        return new Clock();
    }
}

class AliasedAwayFromGlobal
{
    // Line 22: OK — the bare name 'DateTime' is imported from Fixtures\Other, not global
    public function today(): DateTime
    {
        return new DateTime();
    }
}
