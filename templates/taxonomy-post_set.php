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
        // Custom query for posts in this post_set taxonomy term, ordered by episode_number meta
        $query = new WP_Query([
            'post_type' => 'post',
            'tax_query' => [[
                'taxonomy' => 'post_set',
                'field' => 'term_id',
                'terms' => get_queried_object()->term_id,
            ]],
            'meta_key' => 'episode_number',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => -1,
        ]);

        if ($query->have_posts()) :
            // Start the loop
            while ($query->have_posts()) : $query->the_post(); ?>
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

            // Optional: If you want pagination, you can implement it here, but since 'posts_per_page' is -1, all posts show.
            // mh_magazine_lite_pagination(); // Uncomment if you change posts_per_page to a finite number.

        else :
            get_template_part('content', 'none');
        endif;

        // Reset post data to avoid conflicts
        wp_reset_postdata();
        ?>

    </div><!-- #main-content -->

    <?php get_sidebar(); ?>
</div><!-- .mh-wrapper -->

<?php get_footer(); ?>