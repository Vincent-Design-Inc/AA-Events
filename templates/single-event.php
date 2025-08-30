<?php
/**
 * The template for displaying all single posts.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package AA_Events
 */

get_header(); ?>

	<div id="primary" class="aa-events-wrap">
		<main id="main" class="aa-event-single">
            <?php
            while ( have_posts() ) :
                the_post();
                ?>

                <article id="post-<?php echo esc_attr( get_the_ID() ); ?>" <?php post_class( 'aa-event-item' ); ?>>
                    <header class="entry-header">
                        <?php the_title( '<h2 class="entry-title aa-event-title">', '</h2>' ); ?>
                    </header><!-- .entry-header -->

                    <div class="entry-wrap">
                        <div class="entry-content">
                            <?php the_content(); ?>
                        </div><!-- .entry-content -->

                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="entry-thumbnail">
                                <?php the_post_thumbnail( 'medium', array( 'alt' => the_title_attribute( 'echo=0' ) ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div><!-- .entry-wrap -->

                    <footer class="entry-footer">
                        <div class="aa-event-details">
                            <div class="aa-event-date">
                                <strong><?php esc_html_e( 'Date:', 'aa-events' ); ?></strong>
                                <?php
                                $event_raw = get_field( 'event_datetime' );
                                if ( $event_raw ) {
                                    $tz       = wp_timezone();
                                    $ts       = ( new DateTimeImmutable( $event_raw, $tz ) )->getTimestamp();
                                    $human    = wp_date( 'F j, Y, g:i A', $ts );
                                    $iso_attr = wp_date( 'c', $ts );
                                    ?>
                                    <time datetime="<?php echo esc_attr( $iso_attr ); ?>"><?php echo esc_html( $human ); ?></time>
                                    <?php
                                }
                                ?>
                            </div>

                            <?php if ( get_field( 'event_cost' ) ) { ?>
                                <div class="aa-event-cost">
                                    <strong><?php esc_html_e( 'Cost:', 'aa-events' ); ?></strong>
                                    <?php echo esc_html( get_field( 'event_cost' ) ); ?>
                                </div>
                            <?php } ?>

                            <div class="aa-event-location">
                                <strong><?php esc_html_e( 'Location:', 'aa-events' ); ?></strong>
                                <?php
                                if ( get_field( 'event_location_type' ) ) {
                                    // Online event
                                    $location = get_field( 'event_url' );
                                    echo '<a href="' . esc_url( $location ) . '" aria-label="' . esc_attr__( 'View event website for ' . get_the_title(), 'aa-events' ) . '">' . esc_html( $location ) . '</a>';
                                } else {
                                    // In-person event
                                    $location = get_field( 'event_address' );
                                    echo '<p>' . esc_html( $location ) . '</p>';
                                }
                                ?>
                            </div>

                            <?php if ( get_the_terms( get_the_ID(), 'event_type' ) ) { ?>
                                <div class="aa-event-type">
                                    <strong><?php esc_html_e( 'Type:', 'aa-events' ); ?></strong>
                                    <?php the_terms( get_the_ID(), 'event_type', '', ', ', '' ); ?>
                                </div>
                            <?php } ?>

                            <?php if ( get_the_terms( get_the_ID(), 'event_tag' ) ) { ?>
                                <div class="aa-event-tags">
                                    <strong><?php esc_html_e( 'Tags:', 'aa-events' ); ?></strong>
                                    <?php the_terms( get_the_ID(), 'event_tag', '', ', ', '' ); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </footer><!-- .entry-footer -->
                </article><!-- #post-<?php the_ID(); ?> -->

                <?php
                the_post_navigation();
            endwhile; // End of the loop.
            ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
