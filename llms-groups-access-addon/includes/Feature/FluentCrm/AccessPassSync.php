<?php
/**
 * FluentCRM Access Pass Tag Sync (TAG ONLY)
 *
 * Applies / removes pass-assigned-* tags based on llms_group_order
 * assignment state. No custom fields. No scheduler logic.
 */

namespace LLMSGAA\Feature\FluentCrm;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccessPassSync {

    /**
     * Compatibility entry point for PluginRegistrar
     */
    public static function init_hooks(): void {
        self::init();
    }

    /**
     * Entry point
     */
    public static function init(): void {
        ( new self() )->register_hooks();
    }

    /**
     * Meta keys that indicate assignment changes
     */
    const WATCHED_META_KEYS = [
        'student_email',
        'product_id',
        'source_pass_identifier',
    ];

    /**
     * Product → FluentCRM tag mapping
     */
    const PRODUCT_TAG_MAPPING = [
        2639 => 'pass-assigned-annual',
        8117 => 'pass-assigned-renewal',
        997  => 'pass-assigned-pro',
    ];

    /**
     * Capture prior values so we can clean up old assignments
     */
    private static array $prior_meta = [];

    /**
     * In-request debounce: ensure we sync once per order per request
     */
    private static array $sync_seen = [];

    /* --------------------------------------------------------------------- */
    /* Hooks                                                                  */
    /* --------------------------------------------------------------------- */

    private function register_hooks(): void {

        // Capture old meta before change
        add_filter( 'update_post_metadata', [ $this, 'capture_prior_meta' ], 10, 5 );
        add_filter( 'add_post_metadata',    [ $this, 'capture_prior_meta' ], 10, 5 );
        add_filter( 'delete_post_metadata', [ $this, 'capture_prior_meta' ], 10, 5 );

        // React to changes
        add_action( 'updated_post_meta', [ $this, 'on_meta_change' ], 10, 4 );
        add_action( 'added_post_meta',   [ $this, 'on_meta_change' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'on_meta_change' ], 10, 4 );

        // Handle deletes
        add_action( 'wp_trash_post',      [ $this, 'on_post_removed' ], 10 );
        add_action( 'before_delete_post', [ $this, 'on_post_removed' ], 10 );
    }

    /* --------------------------------------------------------------------- */
    /* Meta tracking                                                          */
    /* --------------------------------------------------------------------- */

    public function capture_prior_meta( $check, $post_id, $meta_key ) {

        if ( get_post_type( $post_id ) !== 'llms_group_order' ) {
            return $check;
        }

        if ( ! in_array( $meta_key, self::WATCHED_META_KEYS, true ) ) {
            return $check;
        }

        if ( ! isset( self::$prior_meta[ $post_id ][ $meta_key ] ) ) {
            self::$prior_meta[ $post_id ][ $meta_key ] = get_post_meta( $post_id, $meta_key, true );
        }

        return $check;
    }

    public function on_meta_change( $meta_id, $post_id, $meta_key ) {

        if ( get_post_type( $post_id ) !== 'llms_group_order' ) {
            return;
        }

        if ( ! in_array( $meta_key, self::WATCHED_META_KEYS, true ) ) {
            return;
        }

        $this->sync_from_order( (int) $post_id );
    }

    public function on_post_removed( $post_id ) {
        if ( get_post_type( $post_id ) === 'llms_group_order' ) {
            $this->sync_from_order( (int) $post_id );
        }
    }

    /* --------------------------------------------------------------------- */
    /* Core logic                                                             */
    /* --------------------------------------------------------------------- */

    private function sync_from_order( int $post_id ): void {

        // ---- in-request debounce ----
        if ( isset( self::$sync_seen[ $post_id ] ) ) {
            return;
        }
        self::$sync_seen[ $post_id ] = true;

        $email      = $this->normalize_email( get_post_meta( $post_id, 'student_email', true ) );
        $product_id = absint( get_post_meta( $post_id, 'product_id', true ) );

        if ( $email && $product_id ) {
            $this->sync_tag( $email, $product_id );
        }

        // Clean up old combination if changed
        $old_email   = $this->normalize_email( self::$prior_meta[ $post_id ]['student_email'] ?? '' );
        $old_product = absint( self::$prior_meta[ $post_id ]['product_id'] ?? 0 );

        if (
            $old_email &&
            $old_product &&
            ( $old_email !== $email || $old_product !== $product_id )
        ) {
            $this->sync_tag( $old_email, $old_product );
        }
    }

    private function sync_tag( string $email, int $product_id ): void {

        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        if ( ! isset( self::PRODUCT_TAG_MAPPING[ $product_id ] ) ) {
            return;
        }

        $tag = self::PRODUCT_TAG_MAPPING[ $product_id ];

        $contact = FluentCrmApi( 'contacts' )->createOrUpdate([
            'email' => $email
        ]);

        if ( ! $contact ) {
            return;
        }

        $has_pass = $this->has_any_order( $email, $product_id );

        if ( $has_pass ) {
            $contact->attachTags( [ $tag ] );
        } else {
            $contact->detachTags( [ $tag ] );
        }
    }

    private function has_any_order( string $email, int $product_id ): bool {
        global $wpdb;

        $sql = "
            SELECT 1
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_email
                ON pm_email.post_id = p.ID AND pm_email.meta_key = 'student_email'
            JOIN {$wpdb->postmeta} pm_product
                ON pm_product.post_id = p.ID AND pm_product.meta_key = 'product_id'
            WHERE p.post_type = 'llms_group_order'
              AND p.post_status != 'trash'
              AND pm_email.meta_value = %s
              AND pm_product.meta_value = %d
            LIMIT 1
        ";

        return (bool) $wpdb->get_var(
            $wpdb->prepare( $sql, $email, $product_id )
        );
    }

    private function normalize_email( $email ): string {
        $email = strtolower( trim( (string) $email ) );
        return is_email( $email ) ? $email : '';
    }
}
