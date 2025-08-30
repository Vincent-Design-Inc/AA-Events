<?php
/**
 * The template for displaying archive pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package AA_Events
 */

get_header(); ?>

	<div id="primary" class="aa-events-wrap">
		<main id="main" class="aa-events-grid">

		<?php
		if ( have_posts() ) :
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();
				?>

				<article id="post-<?php echo esc_attr( get_the_ID() ); ?>" <?php post_class( 'aa-event-item' ); ?>>
					<header class="entry-header">
                        <?php the_title( '<h2 class="entry-title aa-event-title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
                    </header><!-- .entry-header -->

                    <div class="entry-excerpt">
                        <?php the_excerpt(); ?>
                    </div><!-- .entry-excerpt -->

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
									echo '<a href="' . esc_url( $location ) . '" aria-label="' . esc_attr__( 'View event website', 'aa-events' ) . '">' . esc_html( $location ) . '</a>';
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
			endwhile;

			the_posts_navigation();
		else :
			?>
			<p><?php esc_html_e( 'No events found.', 'aa-events' ); ?></p>
			<?php
		endif;
		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
