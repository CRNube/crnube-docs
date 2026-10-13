<?php
declare(strict_types=1);

namespace CRNube\Docs\Access;

use CRNube\Docs\Domain\Document;
use CRNube\Docs\Download\DownloadController;

/**
 * The default policy: public documents are open to everyone. A document marked as
 * restricted (by the protected area of CRNube Docs Pro, which replaces this policy) is
 * open only to the staff who manage documents, so deactivating the add-on never makes
 * a restricted document public.
 */
final class StaffOnlyPolicy implements AccessPolicy
{
    public function canAccess(Document $document, ?\WP_User $user): bool
    {
        if (!$document->restricted) {
            return true;
        }
        return $user !== null && $user->ID > 0 && user_can($user, 'edit_crnd_documents');
    }

    public function deniedRedirect(Document $document): ?string
    {
        if (is_user_logged_in()) {
            return null;
        }
        $target = DownloadController::url($document->id, 'view');
        /** This filter is documented in src/Frontend/View.php */
        return (string) apply_filters('crnd_login_url', wp_login_url($target), $target);
    }
}
