<?php

namespace App\Service\Approval;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Point d'entrée unique de la file de validation : regroupe tous les ApprovalProvider. */
final class ApprovalRegistry
{
    /** @param iterable<ApprovalProvider> $providers */
    public function __construct(#[AutowireIterator('app.approval_provider')] private readonly iterable $providers)
    {
    }

    /** @return array<string,ApprovalProvider> indexés par kind() */
    public function all(): array
    {
        $out = [];
        foreach ($this->providers as $p) {
            $out[$p->kind()] = $p;
        }

        return $out;
    }

    public function get(string $kind): ?ApprovalProvider
    {
        return $this->all()[$kind] ?? null;
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (ApprovalProvider $p): int => $p->count(), $this->all()));
    }
}
