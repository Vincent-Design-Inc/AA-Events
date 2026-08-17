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
							<?php $occurrences = aa_events_get_occurrences( get_the_ID() ); ?>
							<?php if ( $occurrences ) : ?>
							<div class="aa-event-date">
								<strong><?php echo esc_html( 1 === count( $occurrences ) ? __( 'Date:', 'aa-events' ) : __( 'Dates:', 'aa-events' ) ); ?></strong>
								<?php
									echo '<span class="aa-event-multiple-dates">';
								foreach ( $occurrences as $index => $occurrence ) {
									echo aa_events_occurrence_time_markup( $occurrence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped markup.
									if ( aa_events_is_recurring_occurrence( $occurrence ) ) {
										echo ' <span class="aa-event-recurring-badge">' . esc_html__( 'Recurring', 'aa-events' ) . '</span>';
									}
									echo ( $index < count( $occurrences ) - 1 ) ? '<br />' : '';
								}
									echo '</span>';
								?>
							</div>
							<?php endif; ?>

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
