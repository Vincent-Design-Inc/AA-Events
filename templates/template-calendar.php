<?php
/**
 * Template Name: Events Calendar
 *
 * @package AA_Events
 */

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();

			// Include the calendar content.
			events_get_template( 'calendar.php' );
		endwhile; // End of the loop.
		?>

	</main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
