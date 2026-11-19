<?php
/**
 * A reviews section: rating summary, review cards, optional disclaimer.
 *
 * Copy to {theme}/scout-reviews/section.php to change the markup.
 *
 * @var array  $reviews    Normalized reviews (Scout_Reviews_Query::normalize()).
 * @var array  $totals     Platform totals (Scout_Reviews_Query::totals()).
 * @var string $layout     grid | row | list.
 * @var string $disclaimer Text from Reviews > Settings, may be empty.
 * @var bool   $filtered   True when a star filter hides some reviews.
 * @var string $all_url    Google's page with every review, may be empty.
 *
 * @package Scout_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="scout-reviews scout-reviews--<?php echo esc_attr( $layout ); ?>" aria-label="<?php esc_attr_e( 'Reviews', 'scout-reviews' ); ?>">
	<?php
	if ( $totals ) {
		echo Scout_Reviews_Render::template( 'summary', array( 'totals' => $totals ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in template.
	}
	?>

	<?php if ( $reviews ) : ?>
		<div class="scout-reviews__list"<?php echo 'row' === $layout ? ' tabindex="0"' : ''; ?>>
			<?php
			foreach ( $reviews as $review ) {
				echo Scout_Reviews_Render::template( 'card', array( 'review' => $review ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in template.
			}
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $filtered ) ) : ?>
		<p class="scout-reviews__selection">
			<?php esc_html_e( 'These are selected reviews.', 'scout-reviews' ); ?>
			<?php if ( ! empty( $all_url ) ) : ?>
				<a href="<?php echo esc_url( $all_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Read all our reviews on Google', 'scout-reviews' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== trim( $disclaimer ) ) : ?>
		<p class="scout-reviews__disclaimer"><?php echo esc_html( $disclaimer ); ?></p>
	<?php endif; ?>
</section>
