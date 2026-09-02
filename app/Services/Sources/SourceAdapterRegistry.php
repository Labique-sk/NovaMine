<?php

namespace App\Services\Sources;

class SourceAdapterRegistry
{
    /**
     * @param  iterable<SourceAdapterInterface>  $adapters
     */
    public function __construct(private readonly iterable $adapters)
    {
    }

    /**
     * @return list<SourceAdapterInterface>
     */
    public function enabled(): array
    {
        return iterator_to_array($this->adapters, false);
    }
}
