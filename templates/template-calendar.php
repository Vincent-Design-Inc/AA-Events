<?php
/**
 * Template Name: Events Calendar
 *
 * @package AA_Events
 */

get_header(); ?>

<div id="primary" class="content-area">

	<?php
	while ( have_posts() ) :
		the_post();

		// Include the calendar content.
		events_get_template( 'calendar.php' );
	endwhile; // End of the loop.
	?>

</div><!-- #primary -->

<?php
get_footer();
