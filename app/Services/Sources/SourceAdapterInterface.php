<?php

namespace App\Services\Sources;

interface SourceAdapterInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    public function collect(array $criteria): array;
}
