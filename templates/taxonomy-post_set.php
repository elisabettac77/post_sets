<?php get_header(); ?>
<div class="mh-wrapper mh-clearfix">
    <div id="main-content" class="mh-loop mh-content" role="main">
        <?php mh_before_page_content(); ?>
        <header class="page-header">
            <h1 class="page-title"><?php single_term_title(); ?></h1>
            <div class="entry-content mh-loop-description">
                <?php echo term_description(); ?>
            </div>
        </header>
        <?php
        // Get the current term ID
        $current_term_id = get_queried_object()->term_id;
        
        // Alternative approach: Use direct SQL to avoid tax_query
        global $wpdb;
        
        // Get post IDs directly from term relationships (avoiding tax_query)
        $term_taxonomy_id = get_term_by('id', $current_term_id, 'post_set')->term_taxonomy_id;
        
        // Properly prepared SQL to get posts in this term
        $post_id_sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d
             AND p.post_type = 'post'
             AND p.post_status = 'publish'",
            $term_taxonomy_id
        );
        
        $current_post_ids = $wpdb->get_col($post_id_sql);
        
        // If we have posts, fetch and sort them efficiently
        if (!empty($current_post_ids)) {
            // Create a safe, comma-separated list of post IDs for the SQL query
            $placeholder_string = implode(',', array_fill(0, count($current_post_ids), '%d'));
            
            // Build the SQL query with placeholders
            $meta_sql = $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND post_id IN ($placeholder_string)",
                'episode_number',
                ...$current_post_ids // Pass the post IDs as individual arguments
            );
        
            // Execute the properly prepared query
            $meta_results = $wpdb->get_results($meta_sql, ARRAY_A);
            
            // Build a map of post_id => episode_number
            $episode_numbers = array();
            foreach ($meta_results as $row) {
                $episode_numbers[$row['post_id']] = (int)$row['meta_value'];
            }
            
            // Sort post IDs by episode number
            $sorted_post_ids = $current_post_ids; // Create a new array to avoid modifying the original
            usort($sorted_post_ids, function($a, $b) use ($episode_numbers) {
                $a_episode = isset($episode_numbers[$a]) ? $episode_numbers[$a] : 0;
                $b_episode = isset($episode_numbers[$b]) ? $episode_numbers[$b] : 0;
                return $a_episode - $b_episode;
            });
            
            // Create a basic query to avoid any global variable conflicts
            $custom_loop = new WP_Query(array(
                'post__in' => $sorted_post_ids,
                'orderby' => 'post__in', // This preserves our custom order
                'posts_per_page' => -1,
                'post_type' => 'post',
                'no_found_rows' => true // Performance optimization
            ));
            
            // Start the loop
            if ($custom_loop->have_posts()) :
                while ($custom_loop->have_posts()) : $custom_loop->the_post();
                    ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <?php
                        if (has_post_thumbnail()) {
                            the_post_thumbnail('medium');
                        }
                        ?>
                        <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="meta">
                            <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
                        </div>
                        <div class="entry-summary">
                            <?php the_excerpt(); ?>
                        </div>
                    </article>
                <?php endwhile;
                // Reset post data to avoid conflicts
                wp_reset_postdata();
            else :
                get_template_part('content', 'none');
            endif;
        } else {
            get_template_part('content', 'none');
        }
        ?>
    </div>    <?php get_sidebar(); ?>
</div><?php get_footer(); ?>
