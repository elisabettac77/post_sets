<?php
/**
 * Template for displaying the archive for a specific post set.
 *
 * @package Post_Sets
 */

get_header();

// Fetch the global $wp_query object to access posts in the current query
global $wp_query;
$posts = $wp_query->posts;
?>
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
        // Check if $posts is not empty
        if ( ! empty( $posts ) ) :
            foreach ( $posts as $post ) :
                setup_postdata( $post ); // Important for using template tags
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <?php
                    if ( has_post_thumbnail() ) {
                        the_post_thumbnail( 'medium' );
                    }
                    ?>
                    <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="meta">
                        <time datetime="<?php echo get_the_date( 'c' ); ?>"><?php echo get_the_date(); ?></time>
                    </div>
                    <div class="entry-summary">
                        <?php the_excerpt(); ?>
                    </div>
                </article>
                <?php
            endforeach;
            wp_reset_postdata();
        else :
            get_template_part( 'content', 'none' );
        endif;
        ?>
    </div><?php get_sidebar(); ?>
</div><?php get_footer(); ?>
