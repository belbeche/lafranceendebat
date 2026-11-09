<?php

namespace App\Twig;

use App\Service\Approval\ApprovalRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Expose approval_count() aux gabarits : nombre de contenus en attente de décision, pour le badge du menu. */
class ApprovalExtension extends AbstractExtension
{
    public function __construct(private readonly ApprovalRegistry $registry)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('approval_count', function (): int {
            try {
                return $this->registry->total();
            } catch (\Throwable) {
                return 0;
            }
        })];
    }
}
