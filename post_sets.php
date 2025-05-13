<?php
/**
 * -----------------------------------------------------------------------------
 * Plugin Name:  Post Sets Manager
 * Description:  Manages post sets with featured images and episode tracking.
 * Version:      1.0
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

// Activation and Deactivation Hooks
register_activation_hook(__FILE__, 'post_sets_activate');
function post_sets_activate() {
    // Ensure taxonomy is registered
    register_post_set_taxonomy();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'post_sets_deactivate');
function post_sets_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// 0. Enqueue Scripts and Styles
function post_sets_scripts_styles() {
    // Use more efficient transient caching approach instead of meta_query
    $has_shortcode = get_transient('post_sets_has_shortcode');
    
    if (false === $has_shortcode) {
        global $wpdb;
        $has_shortcode = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s LIMIT 1",
                '%[post_sets_menu]%'
            )
        );
        
        // Cache result for 12 hours
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
add_action('wp_enqueue_scripts', 'post_sets_scripts_styles');

// Refresh transient when post is saved
function post_sets_refresh_shortcode_transient($post_id) {
    if (!wp_is_post_revision($post_id)) {
        delete_transient('post_sets_has_shortcode');
    }
}
add_action('save_post', 'post_sets_refresh_shortcode_transient');

function post_sets_enqueue_media_scripts($hook) {
    // Only enqueue on taxonomy edit or add screens for 'post_set'
    $screen = get_current_screen();
    if (
        $screen 
        && $screen->taxonomy === 'post_set' 
        && in_array($hook, ['edit-tags.php', 'term.php', 'term-new.php'])
    ) {
        wp_enqueue_media();
        // Enqueue jQuery if not already (usually loaded in admin)
        wp_enqueue_script('jquery');
    }
}

add_action('admin_enqueue_scripts', 'post_sets_enqueue_media_scripts');

// 1. Register Post Set Taxonomy
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
            'assign_terms' => 'edit_posts'
        ],
        'rewrite' => ['slug' => 'post-set'],
        'labels' => [
            'name' => __('Post Sets', 'post_sets'),
            'add_new_item' => __('Add New Post Set', 'post_sets'),
            'edit_item' => __('Edit Post Set', 'post_sets')
        ]
    ]);
}

// 2. Add Featured Image Field
add_action('post_set_edit_form_fields', 'add_post_set_image_field', 10, 2);
add_action('post_set_add_form_fields', 'add_post_set_image_field');

function add_post_set_image_field( $term ) {
    // If $term is not an object, get the term object
    if ( ! is_object( $term ) ) {
        $term = get_term( $term, 'post_set' );
    }
    if ( ! $term || is_wp_error( $term ) ) {
        return; // Bail if invalid term
    }

    // Output nonce field once per form, ideally outside this function if possible
    wp_nonce_field( 'post_set_image_action', 'post_set_image_nonce' );

    $image_id = get_term_meta( $term->term_id, 'post_set_image', true );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="post_set_image"><?php echo esc_html__( 'Featured Image', 'post_sets' ); ?></label>
        </th>
        <td>
            <?php if ( $image_id ) : 
                $image_html = wp_get_attachment_image( $image_id, 'thumbnail' );
                // wp_get_attachment_image() returns safe HTML, but better to allow only safe tags
                echo wp_kses_post( $image_html );
            ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="<?php echo esc_attr( $image_id ); ?>">
                <button type="button" class="button remove-post-set-image"><?php echo esc_html__( 'Remove image', 'post_sets' ); ?></button>
            <?php else : ?>
                <input type="hidden" name="post_set_image" id="post_set_image" value="">
                <button type="button" class="button add-post-set-image"><?php echo esc_html__( 'Add image', 'post_sets' ); ?></button>
            <?php endif; ?>

            <script type="text/javascript">
            jQuery(document).ready(function($){
                var frame;
                $('.add-post-set-image').on('click', function(e) {
                    e.preventDefault();

                    if (frame) {
                        frame.open();
                        return;
                    }

                    frame = wp.media({
                        title: '<?php echo esc_js( __( 'Select or Upload Media Of Choice', 'post_sets' ) ); ?>',
                        button: { text: '<?php echo esc_js( __( 'Use this media', 'post_sets' ) ); ?>' },
                        multiple: false
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#post_set_image').val(attachment.id);

                        // Remove any existing image preview
                        $('.remove-post-set-image').prev('img').remove();

                        // Insert new image preview before the add button
                        $('.add-post-set-image').before('<img src="'+attachment.url+'" style="max-width:100px;" alt="<?php echo esc_js( esc_attr__( 'Selected image', 'post_sets' ) ); ?>">');

                        $('.add-post-set-image').hide();
                        $('.remove-post-set-image').show();
                    });

                    frame.open();
                });

                $('.remove-post-set-image').on('click', function(e) {
                    e.preventDefault();

                    $('#post_set_image').val('');
                    $(this).prev('img').remove();
                    $(this).hide();
                    $('.add-post-set-image').show();
                });

                // On page load, hide remove button if no image
                if ( ! $('#post_set_image').val() ) {
                    $('.remove-post-set-image').hide();
                }
            });
            </script>
        </td>
    </tr>
    <?php
}

function save_post_set_image($term_id) {
    // Check if nonce is set and verified
    if (!isset($_POST['post_set_image_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['post_set_image_nonce'])), 'post_set_image_action')) {
        return;
    }

    if (isset($_POST['post_set_image'])) {
        update_term_meta(
            $term_id,
            'post_set_image',
            absint(wp_unslash($_POST['post_set_image']))
        );
    }
}

add_action('edited_post_set', 'save_post_set_image', 10, 1);
add_action('create_post_set', 'save_post_set_image', 10, 1);

// 3. Episode Number Metabox
add_action('add_meta_boxes', 'add_episode_number_metabox');
function add_episode_number_metabox() {
    add_meta_box(
        'episode_number',
        __('Episode Information', 'post_sets'),
        'render_episode_metabox',
        'post',
        'side'
    );
}

function render_episode_metabox($post) {
    $episode = get_post_meta($post->ID, 'episode_number', true);
    wp_nonce_field('save_episode_number', 'episode_nonce');
    echo '<input type="number" name="episode_number" value="'.esc_attr($episode).'" min="1">';
}

add_action('save_post', 'save_episode_number');

function save_episode_number($post_id) {
    if (
        !isset($_POST['episode_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['episode_nonce'])), 'save_episode_number')
    ) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['episode_number'])) {
        update_post_meta(
            $post_id,
            'episode_number',
            absint(wp_unslash($_POST['episode_number']))
        );
    }
}

// 4. Display Episode Message
add_filter('the_title', 'add_episode_to_title', 10, 2);
function add_episode_to_title($title, $post_id) {
    if (!is_admin() && in_the_loop() && is_singular()) {
        $terms = get_the_terms($post_id, 'post_set');
        if ($terms && !is_wp_error($terms)) {
            $term = reset($terms);
            
            // Use WP_Term_Query instead of WP_Query for more efficient term counting
            $term_id = $term->term_id;
            $total_posts = get_term($term_id)->count;
            
            // Get episode number for current post
            $current = get_post_meta($post_id, 'episode_number', true);
            
            // Ensure current is not greater than total
            $current = $current ? min((int)$current, $total_posts) : 1;
            
            $episode_info = sprintf(
                '<h3 class="episode-subtitle">%s</h3>',
                sprintf(
                    __('Episode %1$d of %2$d in the %3$s set', 'post_sets'),
                    $current,
                    $total_posts,
                    '<a href="'.esc_url(get_term_link($term)).'">'.$term->name.'</a>'
                )
            );
            
            $title .= $episode_info;
        }
    }
    return $title;
}

// 5. Archive Template Handler
add_filter('template_include', 'custom_post_set_template');
function custom_post_set_template($template) {
    if (is_tax('post_set')) {
        // Check if theme has taxonomy-post_set.php
        $theme_template = locate_template('taxonomy-post_set.php');
        if ($theme_template) {
            return $theme_template; // Use theme template if exists
        }
        // Otherwise load plugin template
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/taxonomy-post_set.php';
        if (file_exists($plugin_template)) {
            // Get the current term ID
            $current_term = get_queried_object();
            $posts = post_sets_get_posts_by_post_set($current_term->term_id);

            // Pass the posts to the template
            include($plugin_template); // Include, not return
            return; // важно чтобы template_include не отработал дальше и не подключил шаблон по умолчанию
        }
    }
    return $template;
}
/**
 * Retrieves posts for a given post set, ordered by episode number.
 *
 * @param int $term_id The ID of the post set term.
 * @return array|WP_Error Array of post objects, or WP_Error on failure.
 */
function post_sets_get_posts_by_post_set($term_id) {
    global $wpdb;

    $term = get_term_by('id', $term_id, 'post_set');
    if (!$term) {
        return [];
    }
    $term_taxonomy_id = $term->term_taxonomy_id;

    $current_post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d
             AND p.post_type = 'post'
             AND p.post_status = 'publish'",
            $term_taxonomy_id
        )
    );

    if (empty($current_post_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($current_post_ids), '%d'));
    $sql = "SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s AND post_id IN ($placeholders)";

    $params = array_merge(['episode_number'], $current_post_ids);
    $meta_sql = $wpdb->prepare($sql, ...$params);

    $meta_results = $wpdb->get_results($meta_sql, ARRAY_A);

    $episode_numbers = [];
    foreach ($meta_results as $row) {
        $episode_numbers[$row['post_id']] = (int)$row['meta_value'];
    }

    $sorted_post_ids = $current_post_ids;
    usort($sorted_post_ids, function($a, $b) use ($episode_numbers) {
        $a_episode = isset($episode_numbers[$a]) ? $episode_numbers[$a] : 0;
        $b_episode = isset($episode_numbers[$b]) ? $episode_numbers[$b] : 0;
        return $a_episode - $b_episode;
    });

    $posts = get_posts([
        'post__in' => $sorted_post_ids,
        'orderby' => 'post__in',
        'posts_per_page' => -1,
        'post_type' => 'post',
        'ignore_sticky_posts' => true,
    ]);

    return $posts;
}

// 6. Post Sets Shortcode
function post_sets_menu_shortcode($atts = []) {
    // Parse attributes
    $atts = shortcode_atts([
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ], $atts, 'post_sets_menu');

    $terms = get_terms([
        'taxonomy' => 'post_set', 
        'hide_empty' => $atts['hide_empty'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order']
    ]);

    if (empty($terms)) return '';

    ob_start();
    echo '<div class="post-sets-container">';
    foreach ($terms as $term) {
        $image_id = get_term_meta($term->term_id, 'post_set_image', true);
        $image = $image_id ? wp_get_attachment_image($image_id, 'medium') : '';
        ?>
        <div class="post-set-card">
            <div class="post-set-image"><?php echo wp_kses_post( $image ); ?></div>
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

add_action('init', function() {
    add_shortcode('post_sets_menu', 'post_sets_menu_shortcode');
});

// 7. Update Post Count on Term Save
add_action('edited_term', 'update_post_set_count', 10, 3);
function update_post_set_count($term_id, $tt_id, $taxonomy) {
    if ($taxonomy !== 'post_set') return;
    $term = get_term($term_id, 'post_set');
    update_term_meta($term_id, 'post_count', $term->count);
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
