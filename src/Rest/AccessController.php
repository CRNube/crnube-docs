<?php
declare(strict_types=1);

namespace CRNube\Docs\Rest;

use CRNube\Docs\Access\Groups;
use CRNube\Docs\Access\PrivateStorage;
use CRNube\Docs\Capabilities;
use CRNube\Docs\Plugin;

/**
 * crnd/v1/access: access groups and their members, for the "Access" admin screen.
 * Everything needs the access-management capability.
 */
final class AccessController
{
    private const IMPORT_MAX = 500;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        $ns    = DocumentsController::NAMESPACE;
        $can   = static fn (): bool => current_user_can(Capabilities::MANAGE_ACCESS);
        $group = ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]];
        $text  = ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'];
        $date  = ['type' => 'string', 'pattern' => '^(\d{4}-\d{2}-\d{2})?$', 'default' => ''];

        register_rest_route($ns, '/access', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'overview'],
            'permission_callback' => $can,
        ]);
        register_rest_route($ns, '/access/storage-check', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => static fn (): array => ['protected' => PrivateStorage::selfTest(true)],
            'permission_callback' => $can,
        ]);
        register_rest_route($ns, '/access/groups', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'createGroup'],
            'permission_callback' => $can,
            'args'                => ['name' => $text + ['required' => true, 'minLength' => 1], 'description' => $text + ['default' => '']],
        ]);
        register_rest_route($ns, '/access/groups/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'updateGroup'],
                'permission_callback' => $can,
                'args'                => $group + ['name' => $text + ['minLength' => 1], 'description' => $text],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteGroup'],
                'permission_callback' => $can,
                'args'                => $group,
            ],
        ]);
        register_rest_route($ns, '/access/groups/(?P<id>\d+)/members', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'members'],
                'permission_callback' => $can,
                'args'                => $group,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'addMembers'],
                'permission_callback' => $can,
                'args'                => $group + [
                    'members' => [
                        'type'     => 'array',
                        'required' => true,
                        'minItems' => 1,
                        'maxItems' => self::IMPORT_MAX,
                        'items'    => [
                            'type'       => 'object',
                            'properties' => [
                                'email'   => ['type' => 'string'],
                                'name'    => ['type' => 'string'],
                                'expires' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'notify'  => ['type' => 'boolean', 'default' => false],
                ],
            ],
        ]);
        register_rest_route($ns, '/access/groups/(?P<id>\d+)/members/(?P<user>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'updateMember'],
                'permission_callback' => $can,
                'args'                => $group + ['user' => ['type' => 'integer', 'required' => true], 'expires' => $date],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'removeMember'],
                'permission_callback' => $can,
                'args'                => $group + ['user' => ['type' => 'integer', 'required' => true]],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $portal = Plugin::instance()->portal->url();
        return [
            'groups'  => Groups::all(),
            'storage' => ['protected' => PrivateStorage::selfTest()],
            'portal'  => $portal,
        ];
    }

    public function createGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = Groups::create(trim((string) $request['name']), (string) $request['description']);
        return new \WP_REST_Response(['id' => $id, 'groups' => Groups::all()], 201);
    }

    public function updateGroup(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request['id'];
        $current = $this->group($id);
        if ($current === null) {
            return $this->notFound();
        }
        Groups::update(
            $id,
            $request->has_param('name') ? trim((string) $request['name']) : $current['name'],
            $request->has_param('description') ? (string) $request['description'] : $current['description']
        );
        return new \WP_REST_Response(['groups' => Groups::all()]);
    }

    public function deleteGroup(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($this->group((int) $request['id']) === null) {
            return $this->notFound();
        }
        Groups::delete((int) $request['id']);
        return new \WP_REST_Response(['groups' => Groups::all()]);
    }

    public function members(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($this->group((int) $request['id']) === null) {
            return $this->notFound();
        }
        return new \WP_REST_Response(Groups::members((int) $request['id']));
    }

    /**
     * Adds people by email (one, or a whole CSV import). Unknown emails get a member
     * account; existing users keep their role and only gain the membership.
     */
    public function addMembers(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $groupId = (int) $request['id'];
        $group   = $this->group($groupId);
        if ($group === null) {
            return $this->notFound();
        }

        $added   = 0;
        $created = 0;
        $failed  = [];
        foreach ((array) $request['members'] as $row) {
            $email   = sanitize_email((string) ($row['email'] ?? ''));
            $name    = sanitize_text_field((string) ($row['name'] ?? ''));
            $expires = $this->date((string) ($row['expires'] ?? ''));
            if ($expires === null) {
                $failed[] = ['email' => $email, 'reason' => __('The expiry date must be YYYY-MM-DD.', 'crnube-docs')];
                continue;
            }

            $existed = $email !== '' && get_user_by('email', $email) instanceof \WP_User;
            $userId  = Groups::userForEmail($email, $name);
            if (is_wp_error($userId)) {
                $failed[] = ['email' => $email, 'reason' => $userId->get_error_message()];
                continue;
            }
            Groups::addMember($groupId, $userId, $expires);
            $added++;
            if (!$existed) {
                $created++;
            }
            if ($request['notify']) {
                $this->invite($userId, $group['name']);
            }
        }

        return new \WP_REST_Response([
            'added'   => $added,
            'created' => $created,
            'failed'  => $failed,
            'members' => Groups::members($groupId),
            'groups'  => Groups::all(),
        ]);
    }

    public function updateMember(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $groupId = (int) $request['id'];
        if ($this->group($groupId) === null) {
            return $this->notFound();
        }
        $expires = $this->date((string) $request['expires']);
        if ($expires === null) {
            return new \WP_Error('crnd_bad_date', __('The expiry date must be YYYY-MM-DD.', 'crnube-docs'), ['status' => 400]);
        }
        Groups::addMember($groupId, (int) $request['user'], $expires);
        return new \WP_REST_Response(['members' => Groups::members($groupId), 'groups' => Groups::all()]);
    }

    public function removeMember(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $groupId = (int) $request['id'];
        if ($this->group($groupId) === null) {
            return $this->notFound();
        }
        Groups::removeMember($groupId, (int) $request['user']);
        return new \WP_REST_Response(['members' => Groups::members($groupId), 'groups' => Groups::all()]);
    }

    /** Lets the person know they have access and where to sign in. */
    private function invite(int $userId, string $groupName): void
    {
        $user = get_userdata($userId);
        if (!$user instanceof \WP_User) {
            return;
        }
        $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $portal = Plugin::instance()->portal->loginUrl();
        wp_mail(
            $user->user_email,
            /* translators: %s: site name */
            sprintf(__('You have access to the documents of %s', 'crnube-docs'), $site),
            sprintf(
                /* translators: 1: person's name, 2: group name, 3: sign-in URL, 4: site name */
                __("Hello %1\$s,\n\nYou were given access to the documents of \"%2\$s\".\n\nTo enter, open %3\$s and type this email address. You will receive a code to sign in; no password is needed.\n\n%4\$s", 'crnube-docs'),
                $user->display_name,
                $groupName,
                $portal,
                $site
            )
        );
    }

    /** '' (no expiry), a valid Y-m-d, or null if invalid. */
    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** @return array{id: int, name: string, description: string, members: int, active: int}|null */
    private function group(int $id): ?array
    {
        foreach (Groups::all() as $group) {
            if ($group['id'] === $id) {
                return $group;
            }
        }
        return null;
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error('crnd_not_found', __('The group does not exist.', 'crnube-docs'), ['status' => 404]);
    }
}
