<?php
declare(strict_types=1);

namespace CRNube\Docs\Access;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;

/**
 * Decides whether a document is restricted and to which groups.
 *
 * - A document can be set to "public" or "private" itself, or "inherit" (the default).
 * - Inheriting, it takes the setting of its categories: each category is public, private
 *   or inherits from its parent, so making "Junta Directiva" private covers everything
 *   below it.
 * - A document in several categories is restricted if any of them is private (the safe
 *   choice), for the union of their groups.
 * - "Private" with no groups selected means "any member" (anyone with an active
 *   membership in some group).
 *
 * This class only reads the settings. The protected area (CRNube Docs Pro) writes them
 * and decides who is a member; without it, restricted documents stay staff-only.
 */
final class DocumentAccess
{
    public const META_ACCESS = '_crnd_access';
    public const META_GROUPS = '_crnd_groups';

    public const TERM_ACCESS = 'crnd_access';
    public const TERM_GROUPS = 'crnd_groups';

    public const MODES      = ['inherit', 'public', 'private'];
    public const TERM_MODES = ['', 'public', 'private'];

    /**
     * @param list<int> $categoryIds
     * @return array{restricted: bool, groups: list<int>}
     */
    public static function resolve(int $postId, array $categoryIds): array
    {
        $own = (string) get_post_meta($postId, self::META_ACCESS, true);
        if ($own === 'public') {
            return ['restricted' => false, 'groups' => []];
        }
        if ($own === 'private') {
            return ['restricted' => true, 'groups' => self::ids(get_post_meta($postId, self::META_GROUPS, true))];
        }

        $restricted = false;
        $groups     = [];
        $anyMember  = false;
        foreach ($categoryIds as $termId) {
            $setting = self::categorySetting($termId);
            if ($setting['access'] !== 'private') {
                continue;
            }
            $restricted = true;
            if ($setting['groups'] === []) {
                $anyMember = true;
            }
            $groups = [...$groups, ...$setting['groups']];
        }

        return [
            'restricted' => $restricted,
            'groups'     => $anyMember ? [] : array_values(array_unique($groups)),
        ];
    }

    /**
     * The effective setting of a category: its own, or the nearest ancestor's.
     *
     * @return array{access: string, groups: list<int>, from: int}
     */
    public static function categorySetting(int $termId): array
    {
        $seen = [];
        while ($termId > 0 && !isset($seen[$termId])) {
            $seen[$termId] = true;
            $access        = (string) get_term_meta($termId, self::TERM_ACCESS, true);
            if ($access === 'public' || $access === 'private') {
                return [
                    'access' => $access,
                    'groups' => $access === 'private' ? self::ids(get_term_meta($termId, self::TERM_GROUPS, true)) : [],
                    'from'   => $termId,
                ];
            }
            $term   = get_term($termId, CategoryTaxonomy::NAME);
            $termId = $term instanceof \WP_Term ? $term->parent : 0;
        }
        return ['access' => 'public', 'groups' => [], 'from' => 0];
    }

    /** @return array{restricted: bool, groups: list<int>} */
    public static function forPost(int $postId): array
    {
        $terms = get_the_terms($postId, CategoryTaxonomy::NAME);
        $ids   = is_array($terms) ? array_map(static fn (\WP_Term $t): int => $t->term_id, $terms) : [];
        return self::resolve($postId, $ids);
    }

    /** @return list<int> */
    public static function ids(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('intval', $value)) : [];
    }

    public static function modeOf(int $postId): string
    {
        $mode = (string) get_post_meta($postId, self::META_ACCESS, true);
        return in_array($mode, self::MODES, true) ? $mode : 'inherit';
    }

    /** @return list<int> */
    public static function groupsOf(int $postId): array
    {
        return self::ids(get_post_meta($postId, self::META_GROUPS, true));
    }

    /** @return list<int> */
    public static function documentGroupsSetting(int $termId): array
    {
        return self::ids(get_term_meta($termId, self::TERM_GROUPS, true));
    }

    public static function isDocument(int $postId): bool
    {
        return get_post_type($postId) === DocumentPostType::NAME;
    }
}
