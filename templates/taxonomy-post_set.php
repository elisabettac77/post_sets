<?php
/**
 * Template for displaying the archive for a specific post set.
 *
 * @package Post_Sets
 */

get_header();

// Get the current post_set term object (do not assign to $term)
$current_post_set = get_queried_object();
$term_id = isset( $current_post_set->term_id ) ? (int) $current_post_set->term_id : 0;

// Use your plugin's function to get posts in this set
$post_set_posts = ( $term_id ) ? post_sets_get_posts_by_post_set( $term_id ) : [];

?>
<div class="mh-wrapper mh-clearfix">
    <div id="main-content" class="mh-loop mh-content" role="main">
        <?php mh_before_page_content(); ?>
        <header class="page-header">
            <h1 class="page-title"><?php echo esc_html( single_term_title( '', false ) ); ?></h1>
            <div class="entry-content mh-loop-description">
                <?php
                $description = term_description();
                if ( $description ) {
                    echo wp_kses_post( $description );
                }
                ?>
            </div>
        </header>
        <?php
        if ( ! empty( $post_set_posts ) ) :
            foreach ( $post_set_posts as $single_post ) :
                // Setup global $post for template tags
                setup_postdata( $single_post );
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <?php
                    if ( has_post_thumbnail() ) {
                        the_post_thumbnail( 'medium' );
                    }
                    ?>
                    <h2 class="entry-title">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h2>
                    <div class="meta">
                        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                            <?php echo esc_html( get_the_date() ); ?>
                        </time>
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
    </div>
    <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
