<?php

namespace App\Service\Approval;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Une fonctionnalité qui a des contenus à valider. Pour qu'une nouvelle fonctionnalité apparaisse dans
 * /admin/a-valider, il suffit d'écrire une classe qui implémente cette interface : elle est repérée toute seule.
 */
#[AutoconfigureTag('app.approval_provider')]
interface ApprovalProvider
{
    /** Identifiant court et stable, utilisé dans les formulaires (ex. "position"). */
    public function kind(): string;

    /** Nom affiché au modérateur. */
    public function label(): string;

    /** @return list<ApprovalItem> */
    public function pending(int $limit = 50): array;

    public function count(): int;

    /** Approuve l'élément. Retourne false s'il n'existe plus ou n'est plus en attente. */
    public function approve(int $id): bool;

    /** Refuse l'élément. Retourne false si la fonctionnalité ne permet pas le refus ou si l'élément n'existe plus. */
    public function reject(int $id): bool;
}
