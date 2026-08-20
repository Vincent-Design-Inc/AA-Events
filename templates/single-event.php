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
                            <?php $summary = aa_events_get_recurrence_summary( get_the_ID() ); ?>
                            <?php if ( ! empty( $summary['occurrences'] ) ) : ?>
                            <div class="aa-event-date">
                                <strong><?php echo esc_html( 1 === $summary['total'] ? __( 'Date:', 'aa-events' ) : __( 'Dates:', 'aa-events' ) ); ?></strong>
                                <?php
                                if ( ! empty( $summary['description'] ) ) {
                                    echo '<p class="aa-event-recurrence-summary">' . esc_html( $summary['description'] ) . '</p>';
                                }

                                foreach ( $summary['occurrences'] as $index => $occurrence ) {
									echo aa_events_occurrence_time_markup( $occurrence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped markup.
									if ( aa_events_is_recurring_occurrence( $occurrence ) ) {
										echo ' <span class="aa-event-recurring-badge">' . esc_html__( 'Recurring', 'aa-events' ) . '</span>';
									}
									echo ( $index < count( $summary['occurrences'] ) - 1 ) ? '<br />' : '';
                                }

                                if ( ! empty( $summary['has_more'] ) ) {
                                    ?>
                                    <button
                                        type="button"
                                        class="aa-event-show-more"
                                        data-event-id="<?php echo esc_attr( (string) get_the_ID() ); ?>"
                                        data-offset="<?php echo esc_attr( (string) $summary['next_offset'] ); ?>"
                                        data-window="<?php echo esc_attr( (string) $summary['window'] ); ?>"
                                    ><?php
                                        /* translators: %d: number of additional occurrences revealed per click. */
                                        echo esc_html( sprintf( __( 'Show next %d', 'aa-events' ), $summary['window'] ) );
                                    ?></button>
                                    <?php
                                }
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
                                    echo '<a href="' . esc_url( $location ) . '" aria-label="' . esc_attr__( 'View event website for ' . get_the_title(), 'aa-events' ) . '">' . esc_html( $location ) . '</a>';
                                } else {
                                    // In-person event
                                    $location = get_field( 'event_address' );
                                    echo '<p>' . esc_html( $location ) . '</p>';
                                }
                                ?>
                            </div>

							<?php $contact_email_markup = aa_events_contact_email_markup( get_field( 'event_contact_email' ) ); ?>
							<?php if ( $contact_email_markup ) : ?>
							<div class="aa-event-contact-email">
								<strong><?php echo esc_html( __( 'Contact Email:', 'aa-events' ) ); ?></strong>
								<?php echo wp_kses_post( $contact_email_markup ); ?>
							</div>
							<?php endif; ?>

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

						<?php $registration_link_markup = aa_events_registration_link_markup( get_field( 'registration_link' ) ); ?>
						<?php if ( $registration_link_markup ) : ?>
						<div class="aa-event-registration">
							<?php echo wp_kses_post( $registration_link_markup ); ?>
						</div>
						<?php endif; ?>
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
