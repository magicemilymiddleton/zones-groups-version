<?php
namespace LLMSGAA\Feature\GroupAdmin;

//v2.3d re-output by GPT with feature and UX revisions to v2.3c
//add modal showing group members via button for non-admin users
//v2.3e deprecated
//v2.3f display_name fallback to show first & last name
//revise button spec

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MyGroupsController {

    /** Register shortcodes & hooks */
    public static function init() {
        add_shortcode( 'llmsgaa_my_groups', [ __CLASS__, 'render_my_groups' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_allow_group_access' ] );

        // Members modal: AJAX + button shortcode
        add_action( 'wp_ajax_llmsgaa_members_list', [ __CLASS__, 'ajax_members_list' ] );
        add_shortcode( 'llmsgaa_group_members_button', [ __CLASS__, 'render_members_button_shortcode' ] );
    }

    /** Shortcode: [llmsgaa_group_members_button group_id="123" label="View Members"] */
    public static function render_members_button_shortcode( $atts ) {
        $atts = shortcode_atts([
            'group_id' => 0,
            'label'    => 'View Members',
        ], $atts );

        $gid = (int) $atts['group_id'];
        if ( ! $gid ) return '';

        // Return (do not echo) to avoid stray characters in output.
        return sprintf(
            '<button class="button llmsgaa-open-members" data-group="%d" aria-haspopup="dialog">%s</button>',
            $gid,
            esc_html( $atts['label'] )
        );
    }

    /** Shortcode renderer: [llmsgaa_my_groups] */
    public static function render_my_groups( $atts ) {
        $atts = shortcode_atts( [
            'title'      => 'My Groups',
            'style'      => 'cards', // 'cards', 'table', or 'list'
            'show_stats' => 'true',
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>' . esc_html( $atts['title'] ) . '</h3>
                        <p>You must be logged in to view your groups.</p>
                    </div>';
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Groups where current user has any group role
        $group_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id as group_id, meta_value as role
             FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND meta_key = '_group_role'",
            $user_id
        ) );

        if ( empty( $group_data ) ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>' . esc_html( $atts['title'] ) . '</h3>
                        <p>You do not belong to any groups.</p>
                    </div>';
        }

        // Build view-model
        $groups = [];
        foreach ( $group_data as $group_info ) {
            $group = get_post( $group_info->group_id );
            if ( ! $group || $group->post_status !== 'publish' ) {
                continue;
            }

            $stats = [];
            if ( $atts['show_stats'] === 'true' ) {
                $stats = self::get_group_statistics( $group_info->group_id );
            }

            // Prefer primary_admin; fallback to admin
            $admin_user_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}lifterlms_user_postmeta
                 WHERE post_id = %d AND meta_key = '_group_role' AND meta_value = 'primary_admin'
                 LIMIT 1",
                $group_info->group_id
            ) );
            if ( ! $admin_user_id ) {
                $admin_user_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}lifterlms_user_postmeta
                     WHERE post_id = %d AND meta_key = '_group_role' AND meta_value = 'admin'
                     LIMIT 1",
                    $group_info->group_id
                ) );
            }

            $admin_name = $admin_email = '';
            if ( $admin_user_id ) {
                $admin_user  = get_userdata( $admin_user_id );
                $admin_name  = $admin_user ? $admin_user->display_name : '';
                $admin_email = $admin_user ? $admin_user->user_email   : '';
            }

            $groups[] = [
                'id'           => $group->ID,
                'title'        => $group->post_title,
                'description'  => wp_trim_words( $group->post_excerpt ?: $group->post_content, 20 ),
                'url'          => get_permalink( $group->ID ),
                'role'         => $group_info->role,
                'stats'        => $stats,
                'created_date' => get_the_date( 'F j, Y', $group->ID ),
                'admin_name'   => $admin_name,
                'admin_email'  => $admin_email,
            ];
        }

        if ( empty( $groups ) ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>' . esc_html( $atts['title'] ) . '</h3>
                        <p>No published groups found.</p>
                    </div>';
        }

        switch ( $atts['style'] ) {
            case 'table':
                return self::render_groups_table( $groups, $atts );
            case 'list':
                return self::render_groups_list( $groups, $atts );
            case 'cards':
            default:
                return self::render_groups_cards( $groups, $atts );
        }
    }

    /** Stats */
    private static function get_group_statistics( $group_id ) {
        global $wpdb;

        $active_members = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE post_id = %d AND meta_key = '_group_role'",
            $group_id
        ) );

        $pending_invites = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}lifterlms_group_invitations 
             WHERE group_id = %d",
            $group_id
        ) );

        $available_licenses = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group
                 ON p.ID = pm_group.post_id
                AND pm_group.meta_key = 'group_id'
                AND pm_group.meta_value = %d
             LEFT JOIN {$wpdb->postmeta} pm_email
                 ON p.ID = pm_email.post_id
                AND pm_email.meta_key = 'student_email'
             WHERE p.post_type = 'llms_group_order'
               AND p.post_status = 'publish'
               AND (pm_email.meta_value IS NULL OR pm_email.meta_value = '')",
            $group_id
        ) );

        $assigned_licenses = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group
                 ON p.ID = pm_group.post_id
                AND pm_group.meta_key = 'group_id'
                AND pm_group.meta_value = %d
             INNER JOIN {$wpdb->postmeta} pm_email
                 ON p.ID = pm_email.post_id
                AND pm_email.meta_key = 'student_email'
             WHERE p.post_type = 'llms_group_order'
               AND p.post_status = 'publish'
               AND pm_email.meta_value IS NOT NULL
               AND pm_email.meta_value != ''",
            $group_id
        ) );

        return [
            'active_members'    => absint( $active_members ),
            'pending_invites'   => absint( $pending_invites ),
            'total_members'     => absint( $active_members ) + absint( $pending_invites ),
            'available_licenses'=> absint( $available_licenses ),
            'assigned_licenses' => absint( $assigned_licenses ),
            'total_licenses'    => absint( $available_licenses ) + absint( $assigned_licenses ),
        ];
    }

    /** Cards view */
    private static function render_groups_cards( $groups, $atts ) {
        ob_start();
        // Ensure assets/styles exist even on admin-only pages
        self::members_enqueue_assets(); ?>
        <div class="llmsgaa-my-groups-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <div class="llmsgaa-group-cards">
                <?php foreach ( $groups as $group ): ?>
                <div class="llmsgaa-group-card">
                    <div class="llmsgaa-group-header">
                        <h4 class="llmsgaa-group-title"><?php echo esc_html( $group['title'] ); ?></h4>
                    </div>

                    <div class="llmsgaa-group-details">
                        <div class="llmsgaa-group-date">
                            <strong>Created:</strong> <?php echo esc_html( $group['created_date'] ); ?>
                        </div>
                    </div>

                    <?php if ( $group['description'] ): ?>
                        <p class="llmsgaa-group-description"><?php echo esc_html( $group['description'] ); ?></p>
                    <?php endif; ?>

                    <?php if ( $group['admin_name'] || $group['admin_email'] ): ?>
						<div class="llmsgaa-primary-admin">
							<span class="llmsgaa-primary-admin__label">Group Admin:</span>
							<span class="llmsgaa-primary-admin__value">
								<?php echo esc_html( $group['admin_name'] ); ?>
								<?php if ( $group['admin_email'] ): ?>
									&lt;<a href="mailto:<?php echo esc_attr( $group['admin_email'] ); ?>"><?php echo esc_html( $group['admin_email'] ); ?></a>&gt;
								<?php endif; ?>
							</span>
						</div>
                    <?php endif; ?>

                    <?php if ( ! empty( $group['stats'] ) && $atts['show_stats'] === 'true' ): ?>
                        <div class="llmsgaa-group-stats">
                            <div class="llmsgaa-stat-item">
                                <span class="llmsgaa-stat-icon">👥</span>
                                <span class="llmsgaa-stat-value"><?php echo $group['stats']['total_members']; ?></span>
                                <span class="llmsgaa-stat-label">Users</span>
                            </div>
                            <div class="llmsgaa-stat-item">
                                <span class="llmsgaa-stat-icon">🎫</span>
                                <span class="llmsgaa-stat-value"><?php echo $group['stats']['available_licenses']; ?></span>
                                <span class="llmsgaa-stat-label">Available</span>
                            </div>
                            <div class="llmsgaa-stat-item">
                                <span class="llmsgaa-stat-icon">✅</span>
                                <span class="llmsgaa-stat-value"><?php echo $group['stats']['assigned_licenses']; ?></span>
                                <span class="llmsgaa-stat-label">Assigned</span>
                            </div>
                        </div>
                    <?php endif; ?>

<div class="llmsgaa-group-actions">
    <span class="llmsgaa-role-chip">
        <?php echo $group['role'] === 'admin' ? 'Admin' : 'User'; ?>
    </span>

    <span class="llmsgaa-actions-spacer"></span>

    <?php if ( $group['role'] === 'admin' ): ?>
        <a href="<?php echo esc_url( $group['url'] ); ?>" class="llmsgaa-group-manage-btn">
            Manage Orders
        </a>
    <?php else: ?>
        <?php
        echo do_shortcode(
            '[llmsgaa_group_members_button group_id="' . (int) $group['id'] . '" label="View Members"]'
        );
        ?>
    <?php endif; ?>
</div>

                    <!-- Role badge pinned bottom-left -->


                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Table view */
    private static function render_groups_table( $groups, $atts ) {
        ob_start();
        self::members_enqueue_assets(); ?>
        <div class="llmsgaa-my-groups-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <div class="llmsgaa-table-wrapper">
                <table class="llmsgaa-groups-table">
                    <thead>
                        <tr>
                            <th>Group Name</th>
                            <th>Role</th>
                            <th>Users</th>
                            <th>Licenses</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $groups as $group ): ?>
                        <tr>
                            <td><div class="llmsgaa-group-info"><strong><?php echo esc_html( $group['title'] ); ?></strong></div></td>
                            <td><?php echo ( $group['role'] === 'admin' ) ? 'Admin' : 'Member'; ?></td>
                            <td>
                                <?php if ( ! empty( $group['stats'] ) ): ?>
                                    <?php echo $group['stats']['total_members']; ?>
                                    <?php if ( $group['stats']['pending_invites'] > 0 ): ?>
                                        <small class="llmsgaa-pending">(<?php echo $group['stats']['pending_invites']; ?> pending)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $group['stats'] ) ): ?>
                                    <span class="llmsgaa-license-summary">
                                        <?php echo $group['stats']['assigned_licenses']; ?> / <?php echo $group['stats']['total_licenses']; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $group['created_date'] ); ?></td>
                            <td>
                                <?php if ( $group['role'] === 'admin' ): ?>
                                    <a href="<?php echo esc_url( $group['url'] ); ?>" class="llmsgaa-group-manage-btn">Manage</a>
                                <?php else: ?>
                                    <?php
                                    echo do_shortcode(
                                        '[llmsgaa_group_members_button group_id="' . (int) $group['id'] . '" label="View Members"]'
                                    );
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** List view */
    private static function render_groups_list( $groups, $atts ) {
        ob_start();
        self::members_enqueue_assets(); ?>
        <div class="llmsgaa-my-groups-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <ul class="llmsgaa-groups-list">
                <?php foreach ( $groups as $group ): ?>
                <li class="llmsgaa-group-item">
                    <div class="llmsgaa-group-summary">
                        <strong><?php echo esc_html( $group['title'] ); ?></strong>
                        <span class="llmsgaa-role-badge">(<?php echo $group['role'] === 'admin' ? 'Admin' : 'Member'; ?>)</span>
                        <?php if ( ! empty( $group['stats'] ) ): ?>
                            <span class="llmsgaa-group-stats-inline" style="display:block;margin-top:0.3em;">
                                👥 <?php echo $group['stats']['total_members']; ?> members
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="llmsgaa-group-actions-inline">
                        <?php if ( $group['role'] === 'admin' ): ?>
                            <a href="<?php echo esc_url( $group['url'] ); ?>" class="llmsgaa-group-manage-btn">⚙️ Manage Group</a>
                        <?php else: ?>
                            <?php
                            echo do_shortcode(
                                '[llmsgaa_group_members_button group_id="' . (int) $group['id'] . '" label="View Members"]'
                            );
                            ?>
                        <?php endif; ?>

                        <?php if ( $group['role'] !== 'admin' && ( $group['admin_name'] || $group['admin_email'] ) ): ?>
                            <div class="llmsgaa-group-admin-contact" style="font-size:85%;margin-top:0.25em;">
                                <strong>Primary Admin:</strong>
                                <?php echo esc_html( $group['admin_name'] ); ?>
                                <?php if ( $group['admin_email'] ): ?>
                                    &lt;<a href="mailto:<?php echo esc_attr( $group['admin_email'] ); ?>"><?php echo esc_html( $group['admin_email'] ); ?></a>&gt;
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Allow access on group single for admins (kept from original) */
    public static function maybe_allow_group_access() {
        if ( is_singular( 'llms_group' ) && is_user_logged_in() ) {
            global $wpdb;
            $group_id = get_queried_object_id();
            $user_id  = get_current_user_id();

            $role = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
                 WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
                $user_id,
                $group_id
            ) );

            if ( $role === 'admin' ) {
                return; // Allow access
            }
        }
    }

    /** Ensure modal assets load once and print a single modal container in the footer */
    public static function members_enqueue_assets() {
        static $done = false;
        if ( $done ) return;
        $done = true;

        $h = 'llmsgaa-members-modal';

        // CSS
        wp_register_style( $h, false, [], null );
        wp_add_inline_style( $h, self::members_css() );
        wp_enqueue_style( $h );

        // JS
        wp_register_script( $h, false, [ 'jquery' ], null, true );
        wp_add_inline_script( $h, self::members_js() );
        wp_enqueue_script( $h );

        wp_localize_script( $h, 'LLMSGAA_MEMBERS', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'llmsgaa_members_list' ),
        ] );

        add_action( 'wp_footer', [ __CLASS__, 'print_members_modal' ], 11 );
    }

    /** Single modal container printed once per page */
    public static function print_members_modal() {
        static $printed = false;
        if ( $printed ) return;
        $printed = true; ?>
        <div id="llmsgaaMembersModal" class="llmsgaa-modal" style="display:none" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="llmsgaa-modal__dialog" role="document">
                <button class="llmsgaa-modal__close" aria-label="Close">&times;</button>
                <div class="llmsgaa-modal__body">
                    <div class="llmsgaa-modal__header"><h3>Group Members</h3></div>
                    <div id="llmsgaaMembersContent"><p>Loading…</p></div>
                </div>
            </div>
            <div class="llmsgaa-modal__backdrop"></div>
        </div>
        <?php
    }

    /** AJAX: return Admins + Users */
    public static function ajax_members_list() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        if ( empty( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'llmsgaa_members_list' ) ) {
            wp_send_json_error( [ 'message' => 'Bad nonce' ], 403 );
        }
        $group_id = isset( $_GET['group_id'] ) ? (int) $_GET['group_id'] : 0;
        if ( ! $group_id ) {
            wp_send_json_error( [ 'message' => 'Missing group_id' ], 400 );
        }
        if ( ! self::user_may_view_group_roster( $group_id ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID as user_id, u.display_name, u.user_email, m.meta_value as role
             FROM {$wpdb->prefix}lifterlms_user_postmeta m
             JOIN {$wpdb->users} u ON u.ID = m.user_id
             WHERE m.post_id = %d
               AND m.meta_key = '_group_role'
               AND m.meta_value IN ('admin','member','primary_admin')
             ORDER BY FIELD(m.meta_value,'primary_admin','admin','member'), u.display_name ASC",
            $group_id
        ), ARRAY_A ) ?: [];

        $admins  = [];
        $members = [];
        foreach ( $rows as $r ) {
            $role = strtolower( $r['role'] );
            if ( in_array( $role, [ 'admin', 'primary_admin' ], true ) ) {
                $admins[] = $r;
            } else {
                $members[] = $r;
            }
        }

        ob_start();
        echo '<div class="llmsgaa-roster">';
        echo '<div class="llmsgaa-roster__section"><h4>Admins</h4>' . self::render_people_table( $admins ) . '</div>';
        echo '<div class="llmsgaa-roster__section"><h4>Users</h4>'   . self::render_people_table( $members, 'user' ) . '</div>';
        echo '</div>';
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    /** Capability: staff OR belongs to this group */
    private static function user_may_view_group_roster( $group_id ) {
        if ( current_user_can( 'manage_lifterlms' ) || current_user_can( 'list_users' ) ) {
            return true;
        }
        if ( ! is_user_logged_in() ) {
            return false;
        }
        global $wpdb;
        $uid = get_current_user_id();
        $in  = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}lifterlms_user_postmeta
             WHERE user_id=%d AND post_id=%d AND meta_key='_group_role'
               AND meta_value IN ('admin','member','primary_admin') LIMIT 1",
            $uid,
            $group_id
        ) );
        return (bool) $in;
    }

    /** Table renderer for modal; maps 'member' → 'User' when requested */
    private static function render_people_table( array $rows, $force_member_label = '' ) {
        if ( empty( $rows ) ) return '<p>None</p>';

        $label = function( $role ) use ( $force_member_label ) {
            $r = strtolower( (string) $role );
            if ( $force_member_label === 'user' && $r === 'member' ) return 'User';
            if ( $r === 'member' ) return 'Member';
            if ( $r === 'primary_admin' || $r === 'admin' ) return 'Admin';
            return ucfirst( $r ?: '-' );
        };

        $h = '<table class="widefat striped llmsgaa-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
		/** set display_name fallback to first_name last_name */
		$name = $r['display_name'];
		if ( empty( $name ) ) {
			$fname = get_user_meta( $r['user_id'], 'first_name', true );
			$lname = get_user_meta( $r['user_id'], 'last_name', true );
			$name = trim( "$fname $lname" );
			if ( empty( $name ) ) {
				$u = get_userdata( $r['user_id'] );
				$name = $u ? $u->user_login : '—';
			}
		}
		$name = esc_html( $name );
		
            $email = esc_html( $r['user_email'] ?? '—' );
            $role  = esc_html( $label( $r['role'] ?? '' ) );
            $h    .= "<tr><td>{$name}</td><td><a href=\"mailto:{$email}\">{$email}</a></td><td>{$role}</td></tr>";
        }
        return $h . '</tbody></table>';
    }

    /** Inline CSS for cards, buttons, modal (self-contained) */
    private static function members_css() {
        return <<<CSS
/* --- Card layout tweaks --- */
.llmsgaa-group-card{ position:relative; padding-bottom:52px; }
.llmsgaa-group-title{ margin:0; padding:0; }

/* Card no extra bottom padding (we moved the badge) */
.llmsgaa-group-card{ position:relative; padding-bottom:6px; }
.llmsgaa-group-title{ margin:0; padding:0; }

/* Actions row with chip + button on one line */
.llmsgaa-group-actions{ display:flex; align-items:center; gap:3rem; margin-top:12px; }
.llmsgaa-actions-spacer{ flex:0; }

.llmsgaa-group-header{
margin-bottom: .3rem;
}

/* Role chip (replaces old absolute badge) */
.llmsgaa-role-chip{
  background:#e9eef7;
  color:#2a4c7b;
  font-weight:700;
  font-size:12px;
  letter-spacing:.3px;
  border-radius:999px;
  padding:6px 10px;
  text-transform:uppercase;
  line-height:1;
  display:inline-block;
}

/* Primary Admin line: smaller, break after label */
.llmsgaa-primary-admin{
  margin-top:6px; margin-bottom:6px; font-size:0.92rem; color:#34445b;
}
.llmsgaa-primary-admin__label{
  display:block; font-weight:700; margin-bottom:2px; font-size:0.95rem; 
}
.llmsgaa-primary-admin__value {font-size:0.9rem;}
.llmsgaa-primary-admin__value a{ text-decoration:none; }

/* Kill any divider lines the theme adds between sections */
.llmsgaa-group-details,
.llmsgaa-group-details:before,
.llmsgaa-group-details:after{
margin-top: .2rem;
margin-bottom: .2rem;
  border:0 !important;
  box-shadow:none !important;
  background-image:none !important;
}


/* Buttons: match .llmsgaa-box button and use palette colors */
.llmsgaa-group-manage-btn,
.llmsgaa-open-members {
  border-radius: 6px;
  padding: 0.4em 1em;
  border: 0;
  font-size: 1rem;
  line-height: 1.2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-weight: 600;
  color: #fff;
}

/* Manage Orders → global palette1 */
.llmsgaa-group-manage-btn {
  background-color: var(--global-palette1, #004ea8);
}
.llmsgaa-group-manage-btn:hover {
  filter: brightness(0.95);
}

/* View Members → global palette2 */
.llmsgaa-open-members {
  background-color: var(--global-palette2, #2a4c7b);
}
.llmsgaa-open-members:hover {
  filter: brightness(0.95);
}

/* Actions area */
.llmsgaa-group-actions{ margin-top:10px; }

/* --- Modal fixes --- */
#llmsgaaMembersModal{ position:fixed; inset:0; display:none; z-index:9999; }
.llmsgaa-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:0; }
.llmsgaa-modal__dialog{
  position:relative; z-index:1; max-width:760px; margin:6vh auto;
  background:#fff; border-radius:16px; box-shadow:0 12px 32px rgba(0,0,0,.25);
  padding:16px; overflow:hidden; -webkit-transform:translateZ(0);
}
.llmsgaa-modal__body{ max-height:70vh; overflow:auto; }
.llmsgaa-modal__header h3{ margin:.2em 0 .6em; }
.llmsgaa-modal__close{
  position:absolute; top:10px; right:10px; border:0; background:transparent;
  font-size:28px; line-height:1; cursor:pointer;
}
.llmsgaa-roster__section{ margin-bottom:18px; }
.llmsgaa-roster__section + .llmsgaa-roster__section{ margin-top:14px; }
.llmsgaa-table{ border-collapse:separate; border-spacing:0; }
.llmsgaa-table td, .llmsgaa-table th{ vertical-align:middle; }
CSS;
    }

    /** Inline JS for modal (self-contained) */
    private static function members_js() {
        return <<<JS
(function($){
  function openM(){ $('#llmsgaaMembersModal').show().attr('aria-hidden','false'); }
  function closeM(){ $('#llmsgaaMembersModal').hide().attr('aria-hidden','true'); }
  $(document).on('click','.llmsgaa-open-members',function(e){
    e.preventDefault();
    var gid = $(this).data('group');
    var \$c = $('#llmsgaaMembersContent');
    \$c.html('<p>Loading…</p>');
    openM();
    $.get(LLMSGAA_MEMBERS.ajaxurl, { action:'llmsgaa_members_list', group_id: gid, nonce: LLMSGAA_MEMBERS.nonce })
      .done(function(resp){
        if(resp && resp.success && resp.data && resp.data.html){ \$c.html(resp.data.html); }
        else { \$c.html('<p>Unable to load members.</p>'); }
      })
      .fail(function(){ \$c.html('<p>Error loading members.</p>'); });
  });
  $(document).on('click','.llmsgaa-modal__close,.llmsgaa-modal__backdrop',closeM);
  $(document).on('keydown',function(e){ if(e.key==='Escape'){ closeM(); }});
})(jQuery);
JS;
    }
}
