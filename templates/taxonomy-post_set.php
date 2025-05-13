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

        // Use pre_get_posts filter for more efficient querying instead of a custom WP_Query
        // This approach avoids both the tax_query and meta_key slow query warnings
        
        // First get all post IDs in this term (more efficient than full posts)
        $post_ids = get_posts([
            'fields'      => 'ids',
            'post_type'   => 'post',
            'numberposts' => -1,
            'tax_query'   => [
                [
                    'taxonomy' => 'post_set',
                    'field'    => 'term_id',
                    'terms'    => $current_term_id,
                ]
            ]
        ]);
        
        // If we have posts, fetch and sort them efficiently 
        if (!empty($post_ids)) {
            // Get episode numbers for all posts at once (one query instead of multiple)
            global $wpdb;
            $meta_results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s AND post_id IN (" . implode(',', array_map('intval', $post_ids)) . ")",
                    'episode_number'
                ),
                ARRAY_A
            );
            
            // Build a map of post_id => episode_number
            $episode_numbers = [];
            foreach ($meta_results as $row) {
                $episode_numbers[$row['post_id']] = (int)$row['meta_value'];
            }
            
            // Sort post IDs by episode number
            usort($post_ids, function($a, $b) use ($episode_numbers) {
                $a_episode = isset($episode_numbers[$a]) ? $episode_numbers[$a] : 0;
                $b_episode = isset($episode_numbers[$b]) ? $episode_numbers[$b] : 0;
                return $a_episode - $b_episode;
            });
            
            // Get posts in the sorted order (avoiding tax_query and meta_key)
            $posts = [];
            foreach ($post_ids as $id) {
                $posts[] = get_post($id);
            }
            
            // Set up a custom loop
            if (!empty($posts)) :
                global $post;
                // Start the loop
                foreach ($posts as $post) : 
                    setup_postdata($post); 
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
                <?php endforeach;
                // Reset post data to avoid conflicts
                wp_reset_postdata();
            else :
                get_template_part('content', 'none');
            endif;
        } else {
            get_template_part('content', 'none');
        }
        ?>
    </div><!-- #main-content -->
    <?php get_sidebar(); ?>
</div><!-- .mh-wrapper -->
<?php get_footer(); ?>
