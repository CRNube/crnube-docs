<?php
declare(strict_types=1);

namespace CRNube\Docs\Access;

use CRNube\Docs\Domain\Document;

/**
 * Decides who may open a document. The download controller and the templates only ask
 * this question; how the person signed in (WordPress account, emailed code, SSO) is the
 * concern of the policy the protected area plugs in through the `crnd_access_policy`
 * filter.
 */
interface AccessPolicy
{
    public function canAccess(Document $document, ?\WP_User $user): bool;

    /** Where to send someone who may not open the document; null answers with a 403. */
    public function deniedRedirect(Document $document): ?string;
}
