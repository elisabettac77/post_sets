<?php
/**
 * Template for displaying the archive for a specific post set.
 *
 * @package Post_Sets
 */

get_header();

// Fetch the global $wp_query object to access posts in the current query
global $wp_query;
$queried_posts = $wp_query->posts; // Use a custom variable
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
        // Check if $queried_posts is not empty
        if ( ! empty( $queried_posts ) ) :
            foreach ( $queried_posts as $queried_post ) :
                setup_postdata( $queried_post ); // Use the custom variable
                ?>
                <article id="post-<?php echo $queried_post->ID; ?>" <?php post_class(); ?>>
                    <?php
                    if ( has_post_thumbnail() ) {
                        the_post_thumbnail( 'medium' );
                    }
                    ?>
                    <h2 class="entry-title"><a href="<?php echo get_permalink( $queried_post->ID ); ?>"><?php echo esc_html( get_the_title( $queried_post->ID ) ); ?></a></h2>
                    <div class="meta">
                        <time datetime="<?php echo esc_attr( get_the_date( 'c', $queried_post->ID ) ); ?>"><?php echo esc_html( get_the_date( '', $queried_post->ID ) ); ?></time>
                    </div>
                    <div class="entry-summary">
                        <?php echo esc_html( get_the_excerpt( $queried_post->ID ) ); ?>
                    </div>
                </article>
                <?php
            endforeach;
            wp_reset_postdata();
        else :
            get_template_part( 'content', 'none' );
        endif;
        ?>
    </div>
    <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
