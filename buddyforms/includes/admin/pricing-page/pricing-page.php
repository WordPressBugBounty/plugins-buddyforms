<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pricing Page
 *
 * @package BuddyPress
 */

if ( ! function_exists( 'buddyforms_freemius_checkout_script' ) ) {
	/**
	 * BuddyForms Freemius Checkout Script
	 *
	 * @return void
	 */
	function buddyforms_freemius_checkout_script() {
		if ( str_contains( get_current_screen()->id, 'bundle_screen' ) ) {
			wp_enqueue_script( 'freemius-checkout', 'https://checkout.freemius.com/js/v1/', array(), '1', true );
			wp_enqueue_script( 'pricing-page', plugin_dir_url( __FILE__ ) . 'build/js/pricing-page.js', array( 'freemius-checkout' ), filemtime( plugin_dir_path( __FILE__ ) . 'build/js/pricing-page.js' ), true );
			wp_enqueue_style( 'pricing-page', plugin_dir_url( __FILE__ ) . 'build/css/pricing-page.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'build/css/pricing-page.css' ) );
		}
	}
}
add_action( 'admin_enqueue_scripts', 'buddyforms_freemius_checkout_script' );

if ( ! function_exists( 'buddyforms_bundle_screen_content' ) ) {
	/**
	 * BuddyForms Bundle Screen Content
	 *
	 * @return void
	 */
	function buddyforms_bundle_screen_content() {
		?>

		<div class="buddy-price">
			<div class="buddy-price__container">
				<header class="buddy-price__header">
					<p class="buddy-price__pre-heading">Pricing</p>
					<h1 class="buddy-price__heading">Choose the Best Bundle for You</h1>
					<p class="buddy-price__description">
						Upgrade your free version or join our premium membership community of online business owners who build, grow and
						scale together, with our bundles.
					</p>
				</header>
				<div class="buddy-price__boxes">
					<div class="buddy-price__box">
						<header class="buddy-price__box-header">
							<h2 class="buddy-price__plan">Personal Plan</h2>
							<p class="buddy-price__price">$99.99 / year</p>
							<p class="buddy-price__sites">One Site</p>
							<button class="buddy-price__button" data-purchase-licenses="1">Get started</button>
						</header>
						<hr class="buddy-price__divider" />
						<ul class="buddy-price__features">
							<li class="buddy-price__feature buddy-price__feature--highlight">All plugins & extensions included</li>
							<li class="buddy-price__feature">1 Site License</li>
							<li class="buddy-price__feature">One year of support</li>
							<li class="buddy-price__feature">One year of updates</li>
						</ul>
					</div>
					<div class="buddy-price__box">
						<header class="buddy-price__box-header">
							<h2 class="buddy-price__plan">Professional Plan</h2>
							<p class="buddy-price__price">$149.99 / year</p>
							<p class="buddy-price__sites">Five Sites</p>
							<button class="buddy-price__button" data-purchase-licenses="5">Get started</button>
						</header>
						<hr class="buddy-price__divider" />
						<ul class="buddy-price__features">
							<li class="buddy-price__feature buddy-price__feature--highlight">All plugins & extensions included</li>
							<li class="buddy-price__feature">5 Site Licenses</li>
							<li class="buddy-price__feature">One year of support</li>
							<li class="buddy-price__feature">One year of updates</li>
						</ul>
					</div>
					<div class="buddy-price__box">
						<header class="buddy-price__box-header">
							<h2 class="buddy-price__plan">Agency Plan</h2>
							<p class="buddy-price__price">$249.99 / year</p>
							<p class="buddy-price__sites">Unlimited Sites</p>
							<button class="buddy-price__button" data-purchase-licenses="0">Get started</button>
						</header>
						<hr class="buddy-price__divider" />
						<ul class="buddy-price__features">
							<li class="buddy-price__feature buddy-price__feature--highlight">All plugins & extensions included</li>
							<li class="buddy-price__feature">Unlimited Sites License</li>
							<li class="buddy-price__feature">One year of support</li>
							<li class="buddy-price__feature">One year of updates</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<?php
	}
}
