<?php
declare(strict_types=1);

namespace CRNube\Docs\Access;

use CRNube\Docs\Domain\Document;

/**
 * Every published document is public until the protected area is enabled.
 */
final class PublicAccessPolicy implements AccessPolicy
{
    public function canAccess(Document $document, ?\WP_User $user): bool
    {
        return true;
    }

    public function deniedRedirect(Document $document): ?string
    {
        return null;
    }
}
