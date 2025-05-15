<?php
/**
 * -----------------------------------------------------------------------------
 * Plugin Name:  Post Sets Manager
 * Description:  Manages post sets with featured images and episode tracking.
 * Version:      1.1
 * Author:       Elisabetta Carrara
 * Author URI:   https://elica-webservices.it/
 * Plugin URI:   https://elica-webservices.it/
 * Text Domain:  post_sets
 * Requires PHP: 8.0
 * Requires CP:  2.0
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 */

// -----------------------------------------------------------------------------
// 1. Activation and Deactivation Hooks
// -----------------------------------------------------------------------------

/**
 * Runs on plugin activation.
 *
 * Registers the post_set taxonomy and flushes rewrite rules.
 *
 * @return void
 */
function post_sets_activate() {
    register_post_set_taxonomy();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'post_sets_activate' );

/**
 * Runs on plugin deactivation.
 *
 * Flushes rewrite rules to clean up after the plugin.
 *
 * @return void
 */
function post_sets_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'post_sets_deactivate' );

// -----------------------------------------------------------------------------
// 2. Register Custom Taxonomy: Post Sets
// -----------------------------------------------------------------------------

/**
 * Registers the post_set custom taxonomy.
 *
 * @return void
 */
function register_post_set_taxonomy() {
    register_taxonomy(
        'post_set',
        'post',
        [
            'label'         => esc_html__( 'Post Sets', 'post_sets' ),
            'hierarchical'  => false,
            'public'        => true,
            'show_in_rest'  => true,
            'capabilities'  => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
            'rewrite'       => [ 'slug' => 'post-set' ],
            'labels'        => [
                'name'          => esc_html__( 'Post Sets', 'post_sets' ),
                'add_new_item'  => esc_html__( 'Add New Post Set', 'post_sets' ),
                'edit_item'     => esc_html__( 'Edit Post Set', 'post_sets' ),
            ],
        ]
    );
}
add_action( 'init', 'register_post_set_taxonomy' );

// -----------------------------------------------------------------------------
// 3. Add Featured Image Field for Taxonomy Terms
// -----------------------------------------------------------------------------

/**
 * Adds an image field to post_set taxonomy term edit and add forms.
 *
 * @param mixed $term Term object when editing, empty when adding.
 * @return void
 */
function add_post_set_image_field( $term ) {
    if ( ! is_object( $term ) ) {
        $term = get_term( $term, 'post_set' );
    }
    if ( ! $term || is_wp_error( $term ) ) {
        return;
    }

    $term_id  = isset( $term->term_id ) ? $term->term_id : 0;
    $image_id = get_term_meta( $term_id, 'post_set_image', true );
    wp_nonce_field( 'post_set_image_action', 'post_set_image_nonce' );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="post_set_image"><?php echo esc_html__( 'Featured Image', 'post_sets' ); ?></label>
        </th>
        <td>
            <?php if ( $image_id ) : ?>
                <?php echo wp_kses_post( wp_get_attachment_image( $image_id, 'thumbnail' ) ); ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="<?php echo esc_attr( $image_id ); ?>">
                <button type="button" class="button remove-post-set-image"><?php echo esc_html__( 'Remove image', 'post_sets' ); ?></button>
            <?php else : ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="">
                <button type="button" class="button add-post-set-image"><?php echo esc_html__( 'Add image', 'post_sets' ); ?></button>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}
add_action( 'post_set_edit_form_fields', 'add_post_set_image_field', 10, 2 );
add_action( 'post_set_add_form_fields', 'add_post_set_image_field' );

/**
 * Saves the image ID for a post_set taxonomy term.
 *
 * @param int $term_id Term ID.
 * @return void
 */
function save_post_set_image( $term_id ) {
    // Verify the nonce.
    if (
        ! isset( $_POST['post_set_image_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['post_set_image_nonce'] ) ), 'post_set_image_action' )
    ) {
        return;
    }

    // Save the image ID if it's set.
    if ( isset( $_POST['post_set_image'] ) ) {
        update_term_meta(
            $term_id,
            'post_set_image',
            absint( sanitize_text_field( wp_unslash( $_POST['post_set_image'] ) ) )
        );
    }
}
add_action( 'edited_post_set', 'save_post_set_image', 10, 1 );
add_action( 'create_post_set', 'save_post_set_image', 10, 1 );

/**
 * Adds a metabox for setting the episode number.
 *
 * @return void
 */
function add_episode_number_metabox() {
    add_meta_box(
        'episode_number',
        esc_html__( 'Episode Number', 'post_sets' ),
        'render_episode_number_metabox',
        'post',
        'side'
    );
}
add_action( 'add_meta_boxes', 'add_episode_number_metabox' );

/**
 * Renders the episode number metabox content.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function render_episode_number_metabox( $post ) {
    $value = get_post_meta( $post->ID, '_episode_number', true );
    wp_nonce_field( 'episode_number_nonce_action', 'episode_number_nonce' );
    ?>
    <label for="episode_number"><?php echo esc_html__( 'Enter Episode Number:', 'post_sets' ); ?></label>
    <input type="number" name="episode_number" value="<?php echo esc_attr( $value ); ?>" />
    <?php
}

/**
 * Saves the episode number meta value.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function save_episode_number_metabox( $post_id ) {
    // Verify the nonce before proceeding.
    if ( 
        ! isset( $_POST['episode_number_nonce'] ) || 
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['episode_number_nonce'] ) ), 'episode_number_nonce_action' )
    ) {
        return;
    }

    // Save the episode number if it's set.
    if ( isset( $_POST['episode_number'] ) ) {
        update_post_meta( $post_id, '_episode_number', absint( $_POST['episode_number'] ) );
    }
}
add_action( 'save_post', 'save_episode_number_metabox' );

/**
 * Adds the episode subtitle to the post title.
 *
 * @param string $title   The post title.
 * @param int    $post_id The post ID.
 * @return string Modified title with subtitle if on single post.
 */
function add_episode_subtitle( $title, $post_id ) {
    if ( is_single( $post_id ) ) {
        $episode_number = get_post_meta( $post_id, '_episode_number', true );
        $post_set = wp_get_post_terms( $post_id, 'post_set', [ 'fields' => 'names' ] );
        if ( $episode_number && $post_set ) {
            $title .= '<br><p>' . sprintf(
                /* translators: 1: Episode number, 2: Post set name(s) */
                esc_html__( 'This is episode %1$s in the set %2$s.', 'post_sets' ),
                esc_html( $episode_number ),
                esc_html( implode( ', ', $post_set ) )
            ) . '</p>';
        }
    }
    return $title;
}
add_filter( 'the_title', 'add_episode_subtitle', 10, 2 );

// -----------------------------------------------------------------------------
// 4. Enqueue Scripts and Styles
// -----------------------------------------------------------------------------

/**
 * Enqueues front-end scripts and styles.
 *
 * @return void
 */
function post_sets_scripts_styles() {
    $has_shortcode = get_transient( 'post_sets_has_shortcode' );

    if ( false === $has_shortcode ) {
        global $wpdb;
        $has_shortcode = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s LIMIT 1",
                '%[post_sets_menu]%'
            )
        );
        set_transient( 'post_sets_has_shortcode', ! empty( $has_shortcode ), 12 * HOUR_IN_SECONDS );
    }

    if ( $has_shortcode ) {
        wp_enqueue_style(
            'post-sets-style',
            plugin_dir_url( __FILE__ ) . 'post-sets.css',
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'post-sets.css' ),
            'all'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'post_sets_scripts_styles' );

/**
 * Enqueues admin media scripts for the taxonomy term pages.
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function post_sets_enqueue_media_scripts( $hook ) {
    $screen = get_current_screen();
    if (
        $screen &&
        $screen->taxonomy === 'post_set' &&
        in_array( $hook, [ 'edit-tags.php', 'term.php', 'term-new.php' ], true )
    ) {
        wp_enqueue_media();
        wp_enqueue_script( 'jquery' );
    }
}
add_action( 'admin_enqueue_scripts', 'post_sets_enqueue_media_scripts' );

// -----------------------------------------------------------------------------
// 5. Post Sets Shortcode
// -----------------------------------------------------------------------------

/**
 * Renders a menu of post sets with their images and descriptions.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output of the post sets menu.
 */
function post_sets_menu_shortcode( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ],
        $atts,
        'post_sets_menu'
    );

    $terms = get_terms(
        [
            'taxonomy'   => 'post_set',
            'hide_empty' => $atts['hide_empty'],
            'orderby'    => $atts['orderby'],
            'order'      => $atts['order'],
        ]
    );

    if ( empty( $terms ) ) {
        return '';
    }

    ob_start();
    echo '<div class="post-sets-container">';
    foreach ( $terms as $term ) {
        $image_id = get_term_meta( $term->term_id, 'post_set_image', true );
        $image    = $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '';
        ?>
        <div class="post-set-card">
            <div class="post-set-image"><?php echo wp_kses_post( $image ); ?></div>
            <div class="post-set-content">
                <h3><a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                    <?php echo esc_html( $term->name ); ?>
                </a></h3>
                <p><?php echo esc_html( $term->description ); ?></p>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode( 'post_sets_menu', 'post_sets_menu_shortcode' );

// -----------------------------------------------------------------------------
// 6. Retrieve Posts by Post Set (Improved Function)
// -----------------------------------------------------------------------------

/**
 * Retrieve all posts in a given post set, ordered by episode number (ascending).
 *
 * @param int $term_id The term ID of the post set.
 * @return WP_Post[] Array of posts in the set, sorted by episode number.
 */
function post_sets_get_posts_by_post_set( $term_id ) {
    $term_id = absint( $term_id );
    if ( ! $term_id ) {
        return [];
    }

    // Check if the term exists in 'post_set' taxonomy.
    $term = get_term_by( 'id', $term_id, 'post_set' );
    if ( ! $term || is_wp_error( $term ) ) {
        return [];
    }

    /**
     * phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
     * The following query uses tax_query and meta_key for ordering, which may be slow on very large datasets.
     * This is intentional and necessary for correct functionality in this context.
     */
    $args = [
        'post_type'           => 'post',
        'posts_per_page'      => -1,
        'post_status'         => 'publish',
        'tax_query'           => [
            [
                'taxonomy' => 'post_set',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ],
        ],
        'meta_key'            => '_episode_number', // Using underscore prefix for consistency.
        'orderby'             => 'meta_value_num',
        'order'               => 'ASC',
        'ignore_sticky_posts' => true,
    ];
    $posts = get_posts( $args );
    // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

    return $posts;
}

// -----------------------------------------------------------------------------
// 7. Admin Documentation Page
// -----------------------------------------------------------------------------

/**
 * Adds the admin menu page for plugin documentation.
 *
 * @return void
 */
function post_sets_add_admin_menu() {
    add_menu_page(
        'Post Sets Documentation',      // Page title.
        'Post Sets Docs',                // Menu title.
        'manage_options',                // Capability required.
        'post-sets-docs',                // Menu slug.
        'post_sets_docs_page_callback',  // Callback function to output page content.
        'dashicons-media-document',      // Icon (optional).
        100                              // Position (optional).
    );
}
add_action( 'admin_menu', 'post_sets_add_admin_menu' );

/**
 * Renders the documentation page content.
 *
 * @return void
 */
function post_sets_docs_page_callback() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Post Sets Plugin Documentation', 'post_sets' ); ?></h1>

        <p><?php echo esc_html__( 'Welcome to the Post Sets plugin! This page explains how the plugin works and how you can customize it.', 'post_sets' ); ?></p>

        <h2><?php echo esc_html__( 'How It Works', 'post_sets' ); ?></h2>
        <p>
            <?php 
            echo esc_html__( 
                'The plugin creates a custom taxonomy called', 
                'post_sets' 
            ); 
            ?> <code>post_set</code> <?php 
            echo esc_html__( 
                'that allows you to group posts into sets. This taxonomy behaves like a Tag and allows you to upload a featured image to identify it. It also provides a custom archive template displaying all posts in a selected post set, ordered by a custom meta key', 
                'post_sets' 
            ); 
            ?> <code>_episode_number</code> <?php 
            echo esc_html__( 
                'and shows a subtitle under a single post title to show episode number and a link to the post set.',
                'post_sets' 
            ); 
            ?>
        </p>

        <h2><?php echo esc_html__( 'Using and Customizing the Archive Template', 'post_sets' ); ?></h2>
        <p>
            <?php
            echo esc_html__(
                'To use the template (located in the',
                'post_sets'
            );
            ?> <code>templates/taxonomy-post_set.php</code> <?php
            echo esc_html__(
                'file), copy it to your theme root folder. That way, in case the plugin gets updated it is not overwritten. The specific template included into the plugin is adapted to work with MH Magazine Lite Theme, to make it work with the theme you are using on your website you need to make a copy of the',
                'post_sets'
            );
            ?> <code>archive.php</code> <?php
            echo esc_html__(
                'template in your theme, name it',
                'post_sets'
            );
            ?> <code>taxonomy-post_set.php</code> <?php
            echo esc_html__(
                'and include the custom functionalities of the',
                'post_sets'
            );
            ?> <code>taxonomy-post_set.php</code> <?php
            echo esc_html__(
                'archive you find into the plugin templates folder into it (you can ask an AI to do this adaptation to the archive template for you if you are not confident to do it yourself). Keep in mind the theme first checks if there is a specific template that caters to the Post Set taxonomy into itself first, and then falls back to the templates folder of the plugin if it is not able to find a suitable archive template.',
                'post_sets'
            );
            ?>
        </p>
        <p>
            <?php
            echo esc_html__(
                'The template uses a custom query to fetch posts in the current post set, ordered by the',
                'post_sets'
            );
            ?> <code>_episode_number</code> <?php
            echo esc_html__(
                'meta key. You can modify the query or the HTML markup to better suit your needs.',
                'post_sets'
            );
            ?>
        </p>
        <p>
            <?php
            echo esc_html__(
                'You can also use the shortcode',
                'post_sets'
            );
            ?> <code>[post_sets_menu]</code> <?php
            echo esc_html__(
                'on any post, page or template (if on a template you will have to use the',
                'post_sets'
            );
            ?> <code>do_shortcode()</code> <?php
            echo esc_html__(
                'function to make it work) in your site to show a list of Post Sets.',
                'post_sets'
            );
            ?>
        </p>    

        <h2><?php echo esc_html__( 'Support & Donations', 'post_sets' ); ?></h2>
        <p><?php echo esc_html__( 'If you find this plugin useful and want to support its development, please consider making a donation. Your support is greatly appreciated!', 'post_sets' ); ?></p>
        <p>
            <a href="https://donate.stripe.com/cN228ZaeFajw54QfYY" target="_blank" class="button button-primary">
                <?php echo esc_html__( 'Donate via Stripe.', 'post_sets' ); ?>
            </a>
        </p>

        <h2><?php echo esc_html__( 'Contact & Feedback', 'post_sets' ); ?></h2>
        <p>
            <?php
            echo sprintf(
                /* translators: %s: URL to the GitHub repository */
                wp_kses(
                    __( 'If you have any questions or suggestions, feel free to open an issue on the plugin\'s <a href="%s" target="_blank">Repository</a>.', 'post_sets' ),
                    [
                        'a' => [
                            'href'   => [],
                            'target' => [],
                        ],
                    ]
                ),
                'https://github.com/elisabettac77/post_sets'
            );
            ?>
        </p>
    </div>
    <?php
}
