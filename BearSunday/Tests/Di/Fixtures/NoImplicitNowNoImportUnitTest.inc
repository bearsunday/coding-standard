<?php

namespace Fixtures\Domain\NoImport;

class Enable
{
    // Line 13: OK (both calls) — no `use DateTime;` import in this namespace, so
    // the bare reference resolves to Fixtures\Domain\NoImport\DateTime, not the
    // global class -- per PHP's class-name resolution rules, unqualified class
    // names never fall back to the global namespace.
    public function isValid(): bool
    {
        return new DateTime() < new DateTime('2030-01-01');
    }
}
