<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

 if ( ! defined( 'ABSPATH' ) ) { exit; } $fs = freemius( $VARS['id'] ); $fs_checkout = FS_Checkout_Manager::instance(); $plugin_id = fs_request_get( 'plugin_id' ); if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) { $plugin_id = $fs->get_id(); } $plan_id = fs_request_get( 'plan_id' ); $licenses = fs_request_get( 'licenses' ); $query_params = $fs_checkout->get_query_params( $fs, $plugin_id, $plan_id, $licenses ); $return_url = $fs_checkout->get_checkout_redirect_return_url( $fs ); $query_params['return_url'] = $return_url; $query_params['cancel_url'] = $fs->pricing_url( fs_request_get( 'billing_cycle', 'annual' ), fs_request_get_bool( 'trial' ) ); if ( has_site_icon() ) { $query_params['cancel_icon'] = get_site_icon_url(); } $install_data = array_merge( $fs->get_opt_in_params(), array( 'activation_url' => fs_nonce_url( $fs->_get_admin_page_url( '', array( 'fs_action' => $fs->get_unique_affix() . '_activate_new', 'plugin_id' => $plugin_id, ) ), $fs->get_unique_affix() . '_activate_new' ), ) ); $query_params['install_data'] = json_encode( $install_data ); $query_params['_fs_dashboard_independent'] = true; $redirect_url = $fs_checkout->get_full_checkout_url( $query_params ); if ( ! fs_redirect( $redirect_url ) ) { ?>
		<div class="fs-checkout-process-redirect">
			<div class="fs-checkout-process-redirect__loader">
				<?php fs_include_template( 'ajax-loader.php' ); ?>
			</div>

			<div class="fs-checkout-process-redirect__content">
				<p>
					<?php echo wp_kses( sprintf( fs_text_inline( 'Redirecting, please <a href="%1$s">click here</a> if you\'re stuck...' ), esc_url( $redirect_url ) ), array( 'a' => array( 'href' => true ) ) ); ?>
				</p>
			</div>
		</div>
		<script type="text/javascript">
            jQuery( document ).ready( function ( $ ) {
            	$( '.fs-checkout-process-redirect .fs-ajax-loader' ).show();
            	window.location.href = <?php echo wp_json_encode($redirect_url ); ?>;
            });
		</script>
		<?php
 } 