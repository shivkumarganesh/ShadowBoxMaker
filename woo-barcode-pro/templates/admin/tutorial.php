<?php
/**
 * Tutorial / Onboarding wizard page.
 *
 * Available: $steps (array), $current_step (int), $total_steps (int), $step (array), $is_complete (bool).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap <?php echo $is_complete ? 'wcbp-tut-complete' : ''; ?>">

<div class="wcbp-tutorial-wrap">
	<div class="wcbp-tutorial-card">

		<?php if ( ! $is_complete ) : ?>
		<!-- Progress dots -->
		<div class="wcbp-tut-dots">
		<?php foreach ( $steps as $n => $s ) : ?>
			<button class="wcbp-tut-dot <?php echo $n === $current_step ? 'active' : ''; ?>"
			        data-step="<?php echo esc_attr( $n ); ?>"
			        title="<?php echo esc_attr( $s['title'] ); ?>"></button>
		<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="wcbp-tutorial-icon"><?php echo esc_html( $step['icon'] ); ?></div>
		<h1><?php echo esc_html( $step['title'] ); ?></h1>

		<p><?php
			// Content may contain a <code> tag from sprintf — wp_kses it.
			echo wp_kses( $step['content'], array( 'code' => array() ) );
		?></p>

		<?php if ( ! $is_complete ) : ?>
		<div class="wcbp-tut-actions">
			<?php if ( $step['action_url'] ) : ?>
			<a href="<?php echo esc_url( $step['action_url'] ); ?>" class="wcbp-tut-action-link" target="_blank">
				<?php echo esc_html( $step['action_label'] ); ?>
			</a>
			<?php endif; ?>

			<button id="wcbp-tut-next" class="button button-primary">
				<?php echo $current_step >= $total_steps + 1
					? esc_html__( 'Finish ✓', 'woo-barcode-pro' )
					: esc_html__( 'Next →', 'woo-barcode-pro' ); ?>
			</button>
			<button id="wcbp-tut-skip"><?php esc_html_e( 'Skip tutorial', 'woo-barcode-pro' ); ?></button>
		</div>
		<?php else : ?>
		<div class="wcbp-tut-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-print-queue' ) ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Go to Print Queue →', 'woo-barcode-pro' ); ?>
			</a>
		</div>
		<?php endif; ?>

	</div>
</div>

<?php
wp_localize_script( 'wcbp-tutorial', 'wcbpTutorial', array(
	'ajax_url'    => admin_url( 'admin-ajax.php' ),
	'nonce'       => wp_create_nonce( 'wcbp_tutorial' ),
	'current_step'=> $current_step,
	'total_steps' => $total_steps,
	'page_url'    => admin_url( 'admin.php?page=wcbp-tutorial' ),
) );
wp_print_scripts( 'wcbp-tutorial' );
?>

</div>
