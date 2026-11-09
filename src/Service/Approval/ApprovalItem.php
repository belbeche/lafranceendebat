<?php

namespace App\Service\Approval;

/** Une chose en attente de décision, décrite de la même façon quelle que soit sa nature. */
final class ApprovalItem
{
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly string $detail = '',
        public readonly ?string $sourceUrl = null,
        public readonly bool $ai = false,
        public readonly ?string $link = null,
        public readonly bool $canReject = true,
    ) {
    }
}
