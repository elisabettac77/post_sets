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

register_activation_hook(__FILE__, 'post_sets_activate');
register_deactivation_hook(__FILE__, 'post_sets_deactivate');

function post_sets_activate() {
    register_post_set_taxonomy();
    flush_rewrite_rules();
}

function post_sets_deactivate() {
    flush_rewrite_rules();
}

// -----------------------------------------------------------------------------
// 2. Register Custom Taxonomy: Post Sets
// -----------------------------------------------------------------------------

add_action('init', 'register_post_set_taxonomy');
function register_post_set_taxonomy() {
    register_taxonomy('post_set', 'post', [
        'label' => __('Post Sets', 'post_sets'),
        'hierarchical' => false,
        'public' => true,
        'show_in_rest' => true,
        'capabilities' => [
            'manage_terms' => 'manage_categories',
            'edit_terms' => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts',
        ],
        'rewrite' => ['slug' => 'post-set'],
        'labels' => [
            'name' => __('Post Sets', 'post_sets'),
            'add_new_item' => __('Add New Post Set', 'post_sets'),
            'edit_item' => __('Edit Post Set', 'post_sets'),
        ],
    ]);
}

// -----------------------------------------------------------------------------
// 3. Add Featured Image Field for Taxonomy Terms
// -----------------------------------------------------------------------------

add_action('post_set_edit_form_fields', 'add_post_set_image_field', 10, 2);
add_action('post_set_add_form_fields', 'add_post_set_image_field');
add_action('edited_post_set', 'save_post_set_image', 10, 1);
add_action('create_post_set', 'save_post_set_image', 10, 1);

function add_post_set_image_field($term) {
    if (!is_object($term)) {
        $term = get_term($term, 'post_set');
    }
    if (!$term || is_wp_error($term)) {
        return;
    }

    wp_nonce_field('post_set_image_action', 'post_set_image_nonce');
    $image_id = get_term_meta($term->term_id, 'post_set_image', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="post_set_image"><?php esc_html_e('Featured Image', 'post_sets'); ?></label>
        </th>
        <td>
            <?php if ($image_id): ?>
                <?php echo wp_kses_post(wp_get_attachment_image($image_id, 'thumbnail')); ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="<?php echo esc_attr($image_id); ?>">
                <button type="button" class="button remove-post-set-image"><?php esc_html_e('Remove image', 'post_sets'); ?></button>
            <?php else: ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="">
                <button type="button" class="button add-post-set-image"><?php esc_html_e('Add image', 'post_sets'); ?></button>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

function save_post_set_image($term_id) {
    if (
        !isset($_POST['post_set_image_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['post_set_image_nonce'])), 'post_set_image_action')
    ) {
        return;
    }

    if (isset($_POST['post_set_image'])) {
        update_term_meta(
            $term_id,
            'post_set_image',
            absint(sanitize_text_field(wp_unslash($_POST['post_set_image'])))
        );
    }
}

// -----------------------------------------------------------------------------
// 4. Enqueue Scripts and Styles
// -----------------------------------------------------------------------------

add_action('wp_enqueue_scripts', 'post_sets_scripts_styles');
add_action('admin_enqueue_scripts', 'post_sets_enqueue_media_scripts');

function post_sets_scripts_styles() {
    $has_shortcode = get_transient('post_sets_has_shortcode');

    if (false === $has_shortcode) {
        global $wpdb;
        $has_shortcode = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s LIMIT 1",
                '%[post_sets_menu]%'
            )
        );
        set_transient('post_sets_has_shortcode', !empty($has_shortcode), 12 * HOUR_IN_SECONDS);
    }

    if ($has_shortcode) {
        wp_enqueue_style(
            'post-sets-style',
            plugin_dir_url(__FILE__) . 'post-sets.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'post-sets.css'),
            'all'
        );
    }
}

function post_sets_enqueue_media_scripts($hook) {
    $screen = get_current_screen();
    if (
        $screen &&
        $screen->taxonomy === 'post_set' &&
        in_array($hook, ['edit-tags.php', 'term.php', 'term-new.php'], true)
    ) {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
}

// -----------------------------------------------------------------------------
// 5. Post Sets Shortcode
// -----------------------------------------------------------------------------

add_shortcode('post_sets_menu', 'post_sets_menu_shortcode');
function post_sets_menu_shortcode($atts = []) {
    $atts = shortcode_atts([
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ], $atts, 'post_sets_menu');

    $terms = get_terms([
        'taxonomy' => 'post_set',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    ]);

    if (empty($terms)) {
        return '';
    }

    ob_start();
    echo '<div class="post-sets-container">';
    foreach ($terms as $term) {
        $image_id = get_term_meta($term->term_id, 'post_set_image', true);
        $image = $image_id ? wp_get_attachment_image($image_id, 'medium') : '';
        ?>
        <div class="post-set-card">
            <div class="post-set-image"><?php echo wp_kses_post($image); ?></div>
            <div class="post-set-content">
                <h3><a href="<?php echo esc_url(get_term_link($term)); ?>">
                    <?php echo esc_html($term->name); ?>
                </a></h3>
                <p><?php echo esc_html($term->description); ?></p>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}

// -----------------------------------------------------------------------------
// 6. Retrieve Posts by Post Set (Improved Function)
// -----------------------------------------------------------------------------

function post_sets_get_posts_by_post_set($term_id) {
    global $wpdb;

    $term = get_term_by('id', $term_id, 'post_set');
    if (!$term) {
        return [];
    }

    $current_post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d
             AND p.post_type = 'post'
             AND p.post_status = 'publish'",
            $term->term_taxonomy_id
        )
    );

    if (empty($current_post_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($current_post_ids), '%d'));
    $meta_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND post_id IN ($placeholders)",
            array_merge(['episode_number'], $current_post_ids)
        ),
        ARRAY_A
    );

    $episode_numbers = [];
    foreach ($meta_results as $row) {
        $episode_numbers[$row['post_id']] = (int)$row['meta_value'];
    }

    $sorted_post_ids = $current_post_ids;
    usort($sorted_post_ids, function ($a, $b) use ($episode_numbers) {
        $a_episode = $episode_numbers[$a] ?? 0;
        $b_episode = $episode_numbers[$b] ?? 0;
        return $a_episode - $b_episode;
    });

    return get_posts([
        'post__in' => $sorted_post_ids,
        'orderby' => 'post__in',
        'posts_per_page' => -1,
        'post_type' => 'post',
        'ignore_sticky_posts' => true,
    ]);
}

// Hook to add admin menu
add_action('admin_menu', 'post_sets_add_admin_menu');

function post_sets_add_admin_menu() {
    add_menu_page(
        'Post Sets Documentation',      // Page title
        'Post Sets Docs',                // Menu title
        'manage_options',                // Capability required
        'post-sets-docs',                // Menu slug
        'post_sets_docs_page_callback', // Callback function to output page content
        'dashicons-media-document',     // Icon (optional)
        100                             // Position (optional)
    );
}

function post_sets_docs_page_callback() {
    ?>
    <div class="wrap">
        <h1>Post Sets Plugin Documentation</h1>

        <p>Welcome to the Post Sets plugin! This page explains how the plugin works and how you can customize it.</p>

        <h2>How It Works</h2>
        <p>
            The plugin creates a custom taxonomy called <code>post_set</code> that allows you to group posts into sets. This taxonomy behaves like a tag and allows you to upload a featured image to identify it.
            It also provides a custom archive template to display all posts in a selected post set, ordered by a custom meta field <code>episode_number</code> and shows a subtitle under a single post title to show episode number and a link to the post set.
        </p>

        <h2>Using and Customizing the Archive Template</h2>
        <p>
            To use the template (located in the <code>templates/taxonomy-post_set.php</code> file), copy it to your theme root folder. That way, in case the plugin gets updated it is not overwritten.
            The specific template included into the plugin is adapted to work with MH Magazine Lite Theme, to make it work with the theme you are using on your website you need to make a copy of the <code>archive.php</code> template in your theme, name it <code>taxonomy-post_set.php</code> and include the custom functionalities of the <code>taxonomy-post_set.php</code> archive you find into the plugin templates folder into it (you can ask an AI to do this adaptation to the archive template for you if you are not confident to do it yourself). Keep in mind the theme first checks if there is a specific template that caters to the Post Set taxonomy into itself first, and then falls back to the templates folder of the plugin if it is not able to find a suitable archive template.
        </p>
        <p>
            The template uses a custom query to fetch posts in the current post set, ordered by the <code>episode_number</code> meta key.
            You can modify the query or the HTML markup to better suit your needs.
        </p>
        <p>
            You can also use the shortcode <code>[post_sets_menu]</code> on any post, page or template (if on a template you will have to use the <code>do_shortcode()</code> function to make it work) in your site to show a list of Post Sets.
        </p>    

        <h2>Support & Donations</h2>
        <p>If you find this plugin useful and want to support its development, please consider making a donation. Your support is greatly appreciated!</p>
        <p>
            <a href="https://donate.stripe.com/cN228ZaeFajw54QfYY" target="_blank" class="button button-primary">
                Donate via Stripe.
            </a>
        </p>

        <h2>Contact & Feedback</h2>
        <p>If you have any questions or suggestions, feel free to open an issue on the plugin's <a href="https://github.com/elisabettac77/post_sets" target="_blank">Repository</a>.</p>
    </div>
    <?php
}
