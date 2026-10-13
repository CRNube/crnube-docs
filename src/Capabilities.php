<?php
declare(strict_types=1);

namespace CRNube\Docs;

/**
 * Own capabilities, so a cooperative's staff can manage documents without being able to
 * touch posts, pages or the rest of the site.
 */
final class Capabilities
{
    public const MANAGE_CATEGORIES = 'manage_crnd_categories';
    public const MANAGE_SETTINGS   = 'manage_crnd_settings';
    public const VIEW_REPORTS      = 'view_crnd_reports';
    /** Used by CRNube Docs Pro (reports, protected area); granted here with the rest. */
    public const MANAGE_ACCESS     = 'manage_crnd_access';
    public const MANAGER_ROLE      = 'crnd_manager';

    /** Primitive capabilities the document post type maps its meta capabilities to. */
    public const DOCUMENT_CAPS = [
        'edit_crnd_documents',
        'edit_others_crnd_documents',
        'edit_published_crnd_documents',
        'edit_private_crnd_documents',
        'publish_crnd_documents',
        'read_private_crnd_documents',
        'delete_crnd_documents',
        'delete_others_crnd_documents',
        'delete_published_crnd_documents',
        'delete_private_crnd_documents',
    ];

    /** @return list<string> */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'administrator' => [...self::DOCUMENT_CAPS, self::MANAGE_CATEGORIES, self::MANAGE_SETTINGS, self::VIEW_REPORTS, self::MANAGE_ACCESS],
            'editor', self::MANAGER_ROLE => [...self::DOCUMENT_CAPS, self::MANAGE_CATEGORIES, self::VIEW_REPORTS, self::MANAGE_ACCESS],
            default => [],
        };
    }

    public static function install(): void
    {
        if (!get_role(self::MANAGER_ROLE)) {
            add_role(self::MANAGER_ROLE, __('Document manager', 'crnube-docs'), [
                'read'         => true,
                'upload_files' => true,
            ]);
        }


        foreach (['administrator', 'editor', self::MANAGER_ROLE] as $roleName) {
            $role = get_role($roleName);
            if ($role === null) {
                continue;
            }
            foreach (self::forRole($roleName) as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    public static function uninstall(): void
    {
        $all = [...self::DOCUMENT_CAPS, self::MANAGE_CATEGORIES, self::MANAGE_SETTINGS, self::VIEW_REPORTS, self::MANAGE_ACCESS];
        foreach (wp_roles()->role_objects as $role) {
            foreach ($all as $cap) {
                $role->remove_cap($cap);
            }
        }
        remove_role(self::MANAGER_ROLE);
    }
}
