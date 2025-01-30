<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

 if ( ! defined( 'ABSPATH' ) ) { exit; } wp_enqueue_script( 'jquery' ); wp_enqueue_script( 'json2' ); fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.js' ); fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' ); fs_enqueue_local_style( 'fs_checkout', '/admin/common.css' ); $fs = freemius( $VARS['id'] ); $slug = $fs->get_slug(); $query_params = FS_Contact_Form_Manager::instance()->get_query_params( $fs ); $view_params = array( 'id' => $VARS['id'], 'page' => strtolower( $fs->get_text_inline( 'Contact', 'contact' ) ), ); fs_require_once_template('secure-https-header.php', $view_params); $has_tabs = $fs->_add_tabs_before_content(); if ( $has_tabs ) { $query_params['tabs'] = 'true'; } ?>
	<div id="fs_contact" class="wrap fs-section fs-full-size-wrapper">
		<div id="fs_frame"></div>
		<script type="text/javascript">
			(function ($) {
				$(function () {

					var
					// Keep track of the i-frame height.
					frame_height = 800,
					base_url = '<?php echo WP_FS__ADDRESS ?>',
					src = base_url + '/contact/?<?php echo http_build_query($query_params) ?>#' + encodeURIComponent(document.location.href),

					// Append the i-frame into the DOM.
					frame = $('<i' + 'frame " src="' + src + '" width="100%" height="' + frame_height + 'px" scrolling="no" frameborder="0" style="background: transparent; width: 1px; min-width: 100%;"><\/i' + 'frame>')
						.appendTo('#fs_frame');

					FS.PostMessage.init(base_url);
					FS.PostMessage.receive('height', function (data) {
						var h = data.height;
						if (!isNaN(h) && h > 0 && h != frame_height) {
							frame_height = h;
							$('#fs_frame i' + 'frame').height(frame_height + 'px');
						}
					});
				});
			})(jQuery);
		</script>
	</div>
<?php
 if ( $has_tabs ) { $fs->_add_tabs_after_content(); } 