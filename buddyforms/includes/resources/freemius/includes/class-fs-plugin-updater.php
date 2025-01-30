<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

 if ( ! defined( 'ABSPATH' ) ) { exit; } class FS_Plugin_Updater { private $_fs; private $_logger; private $_update_details; private $_translation_updates; private static $_upgrade_basename = null; const UPDATES_CHECK_CACHE_EXPIRATION = ( WP_FS__TIME_24_HOURS_IN_SEC / 24 ); private static $_INSTANCES = array(); static function instance( Freemius $freemius ) { $key = $freemius->get_id(); if ( ! isset( self::$_INSTANCES[ $key ] ) ) { self::$_INSTANCES[ $key ] = new self( $freemius ); } return self::$_INSTANCES[ $key ]; } private function __construct( Freemius $freemius ) { $this->_fs = $freemius; $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $freemius->get_slug() . '_updater', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK ); $this->filters(); } private function filters() { add_filter( 'plugins_api', array( &$this, 'plugins_api_filter' ), 10, 3 ); $this->add_transient_filters(); add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array( &$this, 'catch_plugin_update_row' ), 9 ); add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array( &$this, 'edit_and_echo_plugin_update_row' ), 11, 2 ); if ( ! $this->_fs->has_any_active_valid_license() ) { add_action( 'admin_head', array( &$this, 'catch_plugin_information_dialog_contents' ) ); } else { add_action( 'admin_footer', array( &$this, '_add_fs_allow_updater_and_dialog_request_param' ) ); } if ( ! WP_FS__IS_PRODUCTION_MODE ) { add_filter( 'http_request_host_is_external', array( $this, 'http_request_host_is_external_filter' ), 10, 3 ); } if ( $this->_fs->is_premium() ) { if ( ! $this->is_correct_folder_name() ) { add_filter( 'upgrader_post_install', array( &$this, '_maybe_update_folder_name' ), 10, 3 ); } add_filter( 'upgrader_pre_install', array( 'FS_Plugin_Updater', '_store_basename_for_source_adjustment' ), 1, 2 ); add_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1, 3 ); if ( ! $this->_fs->has_any_active_valid_license() ) { add_filter( 'wp_prepare_themes_for_js', array( &$this, 'change_theme_update_info_html' ), 10, 1 ); } } } function _add_fs_allow_updater_and_dialog_request_param() { if ( ! $this->is_plugin_information_dialog_for_plugin() ) { return; } ?>
            <script type="text/javascript">
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery( document ).on( 'wp-plugin-updating', function( event, args ) {
                        if ( typeof args === 'object' && args.slug && typeof args.slug === 'string' ) {
                            if ( <?php echo json_encode( $this->_fs->get_slug() ) ?> === args.slug ) {
                                args.fs_allow_updater_and_dialog = true;
                            }
                        }
                    } );
                }
            </script>
            <?php
 } private function is_plugin_information_dialog_for_plugin() { return ( 'plugin-information' === fs_request_get( 'tab', false ) && $this->_fs->get_slug() === fs_request_get_raw( 'plugin', false ) ); } function catch_plugin_information_dialog_contents() { if ( ! $this->is_plugin_information_dialog_for_plugin() ) { return; } add_action( 'admin_footer', array( &$this, 'edit_and_echo_plugin_information_dialog_contents' ), 0, 1 ); ob_start(); } function edit_and_echo_plugin_information_dialog_contents( $hook_suffix ) { if ( 'plugin-information' !== fs_request_get( 'tab', false ) || $this->_fs->get_slug() !== fs_request_get_raw( 'plugin', false ) ) { return; } $license = $this->_fs->_get_license(); $subscription = ( is_object( $license ) && ! $license->is_lifetime() ) ? $this->_fs->_get_subscription( $license->id ) : null; $contents = ob_get_clean(); $install_or_update_button_id_attribute_pos = strpos( $contents, 'id="plugin_install_from_iframe"' ); if ( false === $install_or_update_button_id_attribute_pos ) { $install_or_update_button_id_attribute_pos = strpos( $contents, 'id="plugin_update_from_iframe"' ); } if ( false !== $install_or_update_button_id_attribute_pos ) { $install_or_update_button_start_pos = strrpos( substr( $contents, 0, $install_or_update_button_id_attribute_pos ), '<a' ); $install_or_update_button_end_pos = ( strpos( $contents, '</a>', $install_or_update_button_id_attribute_pos ) + strlen( '</a>' ) ); $modified_contents = substr( $contents, 0, $install_or_update_button_start_pos ); $install_or_update_button = substr( $contents, $install_or_update_button_start_pos, ( $install_or_update_button_end_pos - $install_or_update_button_start_pos ) ); $install_or_update_button = preg_replace( '/(\<a.+)(id="plugin_(install|update)_from_iframe")(.+href=")([^\s]+)(".*\>)(.+)(\<\/a>)/is', is_object( $license ) ? sprintf( '$1$4%s$6%s$8', $this->_fs->checkout_url( is_object( $subscription ) ? ( 1 == $subscription->billing_cycle ? WP_FS__PERIOD_MONTHLY : WP_FS__PERIOD_ANNUALLY ) : WP_FS__PERIOD_LIFETIME, false, array( 'licenses' => $license->quota ) ), fs_text_inline( 'Renew license', 'renew-license', $this->_fs->get_slug() ) ) : sprintf( '$1$4%s$6%s$8', $this->_fs->pricing_url(), fs_text_inline( 'Buy license', 'buy-license', $this->_fs->get_slug() ) ), $install_or_update_button ); $modified_contents .= $install_or_update_button; $modified_contents .= substr( $contents, $install_or_update_button_end_pos ); $contents = $modified_contents; } echo $contents; } private function add_transient_filters() { if ( $this->_fs->is_premium() && $this->_fs->is_registered() && ! FS_Permission_Manager::instance( $this->_fs )->is_essentials_tracking_allowed() ) { $this->_logger->log( 'Opted out sites cannot receive automatic software updates.' ); return; } add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient_update_plugins_filter' ) ); add_filter( 'pre_set_site_transient_update_themes', array( &$this, 'pre_set_site_transient_update_plugins_filter' ) ); } private function remove_transient_filters() { remove_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient_update_plugins_filter' ) ); remove_filter( 'pre_set_site_transient_update_themes', array( &$this, 'pre_set_site_transient_update_plugins_filter' ) ); } function catch_plugin_update_row() { ob_start(); } function edit_and_echo_plugin_update_row( $file, $plugin_data ) { $plugin_update_row = ob_get_clean(); $current = get_site_transient( 'update_plugins' ); if ( ! isset( $current->response[ $file ] ) ) { echo $plugin_update_row; return; } $r = $current->response[ $file ]; $has_beta_update = $this->_fs->has_beta_update(); if ( $this->_fs->has_any_active_valid_license() ) { if ( $has_beta_update ) { $plugin_update_row = preg_replace( '/(\<div.+>)(.+)(\<a.+href="([^\s]+)"([^\<]+)\>.+\<a.+)(\<\/div\>)/is', ( '$1' . sprintf( fs_text_inline( 'There is a %s of %s available.', 'new-version-available', $this->_fs->get_slug() ), $has_beta_update ? fs_text_inline( 'new Beta version', 'new-beta-version', $this->_fs->get_slug() ) : fs_text_inline( 'new version', 'new-version', $this->_fs->get_slug() ), $this->_fs->get_plugin_title() ) . ' ' . '$3' . '$6' ), $plugin_update_row ); } } else { $plugin_update_row = preg_replace( '/(\<div.+>)(.+)(\<a.+href="([^\s]+)"([^\<]+)\>.+\<a.+)(\<\/div\>)/is', ( '$1' . sprintf( fs_text_inline( 'There is a %s of %s available.', 'new-version-available', $this->_fs->get_slug() ), sprintf( '<a href="$4"%s>%s</a>', '$5', $has_beta_update ? fs_text_inline( 'new Beta version', 'new-beta-version', $this->_fs->get_slug() ) : fs_text_inline( 'new version', 'new-version', $this->_fs->get_slug() ) ), $this->_fs->get_plugin_title() ) . ' ' . $this->_fs->version_upgrade_checkout_link( $r->new_version ) . '$6' ), $plugin_update_row ); } if ( $this->_fs->is_plugin() && isset( $r->upgrade_notice ) && strlen( trim( $r->upgrade_notice ) ) > 0 ) { $slug = $this->_fs->get_slug(); $upgrade_notice_html = sprintf( '<p class="notice fs-upgrade-notice fs-slug-%1$s fs-type-%2$s" data-slug="%1$s" data-type="%2$s"><strong>%3$s</strong> %4$s</p>', $slug, $this->_fs->get_module_type(), fs_text_inline( 'Important Upgrade Notice:', 'upgrade_notice', $slug ), esc_html( $r->upgrade_notice ) ); $plugin_update_row = str_replace( '</div>', '</div>' . $upgrade_notice_html, $plugin_update_row ); } echo $plugin_update_row; } function change_theme_update_info_html( $prepared_themes ) { $theme_basename = $this->_fs->get_plugin_basename(); if ( ! isset( $prepared_themes[ $theme_basename ] ) ) { return $prepared_themes; } $themes_update = get_site_transient( 'update_themes' ); if ( ! isset( $themes_update->response[ $theme_basename ] ) || empty( $themes_update->response[ $theme_basename ]['package'] ) ) { return $prepared_themes; } $prepared_themes[ $theme_basename ]['update'] = preg_replace( '/(\<p.+>)(.+)(\<a.+\<a.+)\.(.+\<\/p\>)/is', '$1 $2 ' . $this->_fs->version_upgrade_checkout_link( $themes_update->response[ $theme_basename ]['new_version'] ) . '$4', $prepared_themes[ $theme_basename ]['update'] ); $prepared_themes[ $theme_basename ]['hasPackage'] = false; return $prepared_themes; } function http_request_host_is_external_filter( $allow, $host, $url ) { return ( false !== strpos( $host, 'freemius' ) ) ? true : $allow; } function pre_set_site_transient_update_plugins_filter( $transient_data ) { $this->_logger->entrance(); $module_type = $this->_fs->get_module_type() . 's'; if ( "pre_set_site_transient_update_{$module_type}" !== current_filter() ) { return $transient_data; } if ( empty( $transient_data ) || defined( 'WP_FS__UNINSTALL_MODE' ) ) { return $transient_data; } global $wp_current_filter; $current_plugin_version = $this->_fs->get_plugin_version(); if ( ! empty( $wp_current_filter ) && 'upgrader_process_complete' === $wp_current_filter[0] ) { if ( is_null( $this->_update_details ) || ( is_object( $this->_update_details ) && $this->_update_details->new_version !== $current_plugin_version ) ) { $this->_update_details = null; $current_plugin_version = $this->_fs->get_plugin_version( true ); } } if ( ! isset( $this->_update_details ) ) { $new_version = $this->_fs->get_update( false, fs_request_get_bool( 'force-check' ), FS_Plugin_Updater::UPDATES_CHECK_CACHE_EXPIRATION, $current_plugin_version ); $this->_update_details = false; if ( is_object( $new_version ) && $this->is_new_version_premium( $new_version ) ) { $this->_logger->log( 'Found newer plugin version ' . $new_version->version ); $this->_update_details = $this->get_update_details( $new_version ); } } $basename = $this->_fs->premium_plugin_basename(); if ( is_object( $this->_update_details ) ) { if ( isset( $transient_data->no_update ) ) { unset( $transient_data->no_update[ $basename ] ); } if ( ! isset( $transient_data->response ) ) { $transient_data->response = array(); } $transient_data->response[ $basename ] = $this->_fs->is_plugin() ? $this->_update_details : (array) $this->_update_details; } else { if ( isset( $transient_data->response ) ) { unset( $transient_data->response[ $basename ] ); } if ( ! isset( $transient_data->no_update ) ) { $transient_data->no_update = array(); } $transient_data->no_update[ $basename ] = $this->_fs->is_plugin() ? (object) array( 'id' => $basename, 'slug' => $this->_fs->get_slug(), 'plugin' => $basename, 'new_version' => $this->_fs->get_plugin_version(), 'url' => '', 'package' => '', 'icons' => array(), 'banners' => array(), 'banners_rtl' => array(), 'tested' => '', 'requires_php' => '', 'compatibility' => new stdClass(), ) : array( 'theme' => $basename, 'new_version' => $this->_fs->get_plugin_version(), 'url' => '', 'package' => '', 'requires' => '', 'requires_php' => '', ); } $slug = $this->_fs->get_slug(); if ( $this->can_fetch_data_from_wp_org() ) { if ( ! isset( $this->_translation_updates ) ) { $this->_translation_updates = array(); $translation_updates = $this->fetch_wp_org_module_translation_updates( $module_type, $slug ); if ( ! empty( $translation_updates ) ) { $this->_translation_updates = $translation_updates; } } if ( ! empty( $this->_translation_updates ) ) { $all_translation_updates = ( isset( $transient_data->translations ) && is_array( $transient_data->translations ) ) ? $transient_data->translations : array(); $current_plugin_translation_updates_map = array(); foreach ( $all_translation_updates as $key => $translation_update ) { if ( $module_type === ( $translation_update['type'] . 's' ) && $slug === $translation_update['slug'] ) { $current_plugin_translation_updates_map[ $translation_update['language'] ] = $translation_update; unset( $all_translation_updates[ $key ] ); } } foreach ( $this->_translation_updates as $translation_update ) { $lang = $translation_update['language']; if ( ! isset( $current_plugin_translation_updates_map[ $lang ] ) || version_compare( $translation_update['version'], $current_plugin_translation_updates_map[ $lang ]['version'], '>' ) ) { $current_plugin_translation_updates_map[ $lang ] = $translation_update; } } $transient_data->translations = array_merge( $all_translation_updates, array_values( $current_plugin_translation_updates_map ) ); } } return $transient_data; } function get_update_details( FS_Plugin_Tag $new_version ) { $update = new stdClass(); $update->slug = $this->_fs->get_slug(); $update->new_version = $new_version->version; $update->url = WP_FS__ADDRESS; $update->package = $new_version->url; $update->tested = self::get_tested_wp_version( $new_version->tested_up_to_version ); $update->requires = $new_version->requires_platform_version; $update->requires_php = $new_version->requires_programming_language_version; $icon = $this->_fs->get_local_icon_url(); if ( ! empty( $icon ) ) { $update->icons = array( 'default' => $icon, ); } if ( $this->_fs->is_premium() ) { $latest_tag = $this->_fs->_fetch_latest_version( $this->_fs->get_id(), false ); if ( isset( $latest_tag->readme ) && isset( $latest_tag->readme->upgrade_notice ) && ! empty( $latest_tag->readme->upgrade_notice ) ) { $update->upgrade_notice = $latest_tag->readme->upgrade_notice; } } $update->{$this->_fs->get_module_type()} = $this->_fs->get_plugin_basename(); return $update; } private function is_new_version_premium( FS_Plugin_Tag $new_version ) { $params = fs_parse_url_params( $new_version->url ); return ( isset( $params['is_premium'] ) && 'true' == $params['is_premium'] ); } function set_update_data( FS_Plugin_Tag $new_version ) { $this->_logger->entrance(); if ( ! $this->is_new_version_premium( $new_version ) ) { return; } $transient_key = "update_{$this->_fs->get_module_type()}s"; $transient_data = get_site_transient( $transient_key ); $transient_data = is_object( $transient_data ) ? $transient_data : new stdClass(); $basename = $this->_fs->get_plugin_basename(); $is_plugin = $this->_fs->is_plugin(); if ( ! isset( $transient_data->response ) || ! is_array( $transient_data->response ) ) { $transient_data->response = array(); } else if ( ! empty( $transient_data->response[ $basename ] ) ) { $version = $is_plugin ? ( ! empty( $transient_data->response[ $basename ]->new_version ) ? $transient_data->response[ $basename ]->new_version : null ) : ( ! empty( $transient_data->response[ $basename ]['new_version'] ) ? $transient_data->response[ $basename ]['new_version'] : null ); if ( $version == $new_version->version ) { return; } } $this->remove_transient_filters(); $this->_update_details = $this->get_update_details( $new_version ); $transient_data->response[ $basename ] = $is_plugin ? $this->_update_details : (array) $this->_update_details; if ( ! isset( $transient_data->checked ) || ! is_array( $transient_data->checked ) ) { $transient_data->checked = array(); } $transient_data->checked[ $basename ] = $this->_fs->get_plugin_version(); $transient_data->last_checked = time(); set_site_transient( $transient_key, $transient_data ); $this->add_transient_filters(); } function delete_update_data() { $this->_logger->entrance(); $transient_key = "update_{$this->_fs->get_module_type()}s"; $transient_data = get_site_transient( $transient_key ); $basename = $this->_fs->get_plugin_basename(); if ( ! is_object( $transient_data ) || ! isset( $transient_data->response ) || ! is_array( $transient_data->response ) || empty( $transient_data->response[ $basename ] ) ) { return; } unset( $transient_data->response[ $basename ] ); $this->remove_transient_filters(); set_site_transient( $transient_key, $transient_data ); $this->add_transient_filters(); } static function _fetch_plugin_info_from_repository( $action, $args ) { $url = $http_url = 'http://api.wordpress.org/plugins/info/1.2/'; $url = add_query_arg( array( 'action' => $action, 'request' => $args, ), $url ); if ( wp_http_supports( array( 'ssl' ) ) ) { $url = set_url_scheme( $url, 'https' ); } $request = wp_remote_get( $url, array( 'timeout' => 15 ) ); if ( is_wp_error( $request ) ) { return false; } $res = json_decode( wp_remote_retrieve_body( $request ), true ); if ( is_array( $res ) ) { $res = (object) $res; } if ( ! is_object( $res ) || isset( $res->error ) ) { return false; } return $res; } private function can_fetch_data_from_wp_org() { return ( $this->_fs->is_org_repo_compliant() && $this->_fs->is_freemium() ); } private function fetch_wp_org_module_translation_updates( $module_type, $slug ) { $plugin_data = $this->_fs->get_plugin_data(); $locales = array_values( get_available_languages() ); $locales = apply_filters( "{$module_type}_update_check_locales", $locales ); $locales = array_unique( $locales ); $plugin_basename = $this->_fs->get_plugin_basename(); if ( 'themes' === $module_type ) { $plugin_basename = $slug; } global $wp_version; $request_args = array( 'timeout' => 15, 'body' => array( "{$module_type}" => json_encode( array( "{$module_type}" => array( $plugin_basename => array( 'Name' => trim( str_replace( $this->_fs->get_plugin()->premium_suffix, '', $plugin_data['Name'] ) ), 'Author' => $plugin_data['Author'], ) ) ) ), 'translations' => json_encode( $this->get_installed_translations( $module_type, $slug ) ), 'locale' => json_encode( $locales ) ), 'user-agent' => ( 'WordPress/' . $wp_version . '; ' . home_url( '/' ) ) ); $url = "http://api.wordpress.org/{$module_type}/update-check/1.1/"; if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) { $url = set_url_scheme( $url, 'https' ); } $raw_response = Freemius::safe_remote_post( $url, $request_args, WP_FS__TIME_24_HOURS_IN_SEC, WP_FS__TIME_12_HOURS_IN_SEC, false ); if ( is_wp_error( $raw_response ) ) { return null; } $response = json_decode( wp_remote_retrieve_body( $raw_response ), true ); if ( ! is_array( $response ) ) { return null; } if ( ! isset( $response['translations'] ) || empty( $response['translations'] ) ) { return null; } return $response['translations']; } private function get_installed_translations( $module_type, $slug ) { if ( function_exists( 'wp_get_installed_translations' ) ) { return wp_get_installed_translations( $module_type ); } $dir = "/{$module_type}"; if ( ! is_dir( WP_LANG_DIR . $dir ) ) return array(); $files = scandir( WP_LANG_DIR . $dir ); if ( ! $files ) return array(); $language_data = array(); foreach ( $files as $file ) { if ( 0 !== strpos( $file, $slug ) ) { continue; } if ( '.' === $file[0] || is_dir( WP_LANG_DIR . "{$dir}/{$file}" ) ) { continue; } if ( substr( $file, -3 ) !== '.po' ) { continue; } if ( ! preg_match( '/(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?).po/', $file, $match ) ) { continue; } if ( ! in_array( substr( $file, 0, -3 ) . '.mo', $files ) ) { continue; } list( , $textdomain, $language ) = $match; if ( '' === $textdomain ) { $textdomain = 'default'; } $language_data[ $textdomain ][ $language ] = wp_get_pomo_file_data( WP_LANG_DIR . "{$dir}/{$file}" ); } return $language_data; } function plugins_api_filter( $data, $action = '', $args = null ) { $this->_logger->entrance(); if ( ( 'plugin_information' !== $action ) || ! isset( $args->slug ) ) { return $data; } $addon = false; $is_addon = false; $addon_version = false; if ( $this->_fs->get_slug() !== $args->slug ) { $addon = $this->_fs->get_addon_by_slug( $args->slug ); if ( ! is_object( $addon ) ) { return $data; } if ( $this->_fs->is_addon_activated( $addon->id ) ) { $addon_version = $this->_fs->get_addon_instance( $addon->id )->get_plugin_version(); } else if ( $this->_fs->is_addon_installed( $addon->id ) ) { $addon_plugin_data = get_plugin_data( ( WP_PLUGIN_DIR . '/' . $this->_fs->get_addon_basename( $addon->id ) ), false, false ); if ( ! empty( $addon_plugin_data ) ) { $addon_version = $addon_plugin_data['Version']; } } $is_addon = true; } $plugin_in_repo = false; if ( ! $is_addon && $this->can_fetch_data_from_wp_org() ) { $data = self::_fetch_plugin_info_from_repository( $action, $args ); $plugin_in_repo = ( false !== $data ); } if ( ! $plugin_in_repo ) { $data = $args; $plugin_local_data = $this->_fs->get_plugin_data(); $data->name = $plugin_local_data['Name']; $data->author = $plugin_local_data['Author']; $data->sections = array( 'description' => 'Upgrade ' . $plugin_local_data['Name'] . ' to latest.', ); } $plugin_version = $is_addon ? $addon_version : $this->_fs->get_plugin_version(); $new_version = $this->get_latest_download_details( $is_addon ? $addon->id : false, $plugin_version ); if ( ! is_object( $new_version ) || empty( $new_version->version ) ) { $data->version = $plugin_version; } else { if ( $is_addon ) { $data->name = $addon->title . ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' ); $data->slug = $addon->slug; $data->url = WP_FS__ADDRESS; $data->package = $new_version->url; } if ( ! $plugin_in_repo ) { $data->last_updated = ! is_null( $new_version->updated ) ? $new_version->updated : $new_version->created; $data->requires = $new_version->requires_platform_version; $data->requires_php = $new_version->requires_programming_language_version; $data->tested = $new_version->tested_up_to_version; } $data->version = $new_version->version; $data->download_link = $new_version->url; if ( isset( $new_version->readme ) && is_object( $new_version->readme ) ) { $new_version_readme_data = $new_version->readme; if ( isset( $new_version_readme_data->sections ) ) { $new_version_readme_data->sections = (array) $new_version_readme_data->sections; } else { $new_version_readme_data->sections = array(); } if ( isset( $data->sections ) ) { if ( isset( $data->sections['screenshots'] ) ) { $new_version_readme_data->sections['screenshots'] = $data->sections['screenshots']; } if ( isset( $data->sections['reviews'] ) ) { $new_version_readme_data->sections['reviews'] = $data->sections['reviews']; } } if ( isset( $new_version_readme_data->banners ) ) { $new_version_readme_data->banners = (array) $new_version_readme_data->banners; } else if ( isset( $data->banners ) ) { $new_version_readme_data->banners = $data->banners; } $wp_org_sections = array( 'author', 'author_profile', 'rating', 'ratings', 'num_ratings', 'support_threads', 'support_threads_resolved', 'active_installs', 'added', 'homepage' ); foreach ( $wp_org_sections as $wp_org_section ) { if ( isset( $data->{$wp_org_section} ) ) { $new_version_readme_data->{$wp_org_section} = $data->{$wp_org_section}; } } $data = $new_version_readme_data; } } if ( ! empty( $data->tested ) ) { $data->tested = self::get_tested_wp_version( $data->tested ); } return $data; } private static function get_tested_wp_version( $tested_up_to ) { $current_wp_version = get_bloginfo( 'version' ); return ( ! empty($tested_up_to) && fs_starts_with( $current_wp_version, $tested_up_to . '.' ) ) ? $current_wp_version : $tested_up_to; } private function get_latest_download_details( $addon_id = false, $newer_than = false, $fetch_readme = true ) { return $this->_fs->_fetch_latest_version( $addon_id, true, FS_Plugin_Updater::UPDATES_CHECK_CACHE_EXPIRATION, $newer_than, $fetch_readme ); } private function is_correct_folder_name() { return ( $this->_fs->get_target_folder_name() == trim( dirname( $this->_fs->get_plugin_basename() ), '/\\' ) ); } function _maybe_update_folder_name( $response, $hook_extra, $result ) { $basename = $this->_fs->get_plugin_basename(); if ( true !== $response || empty( $hook_extra ) || empty( $hook_extra['plugin'] ) || $basename !== $hook_extra['plugin'] ) { return $response; } $active_plugins_basenames = get_option( 'active_plugins' ); foreach ( $active_plugins_basenames as $key => $active_plugin_basename ) { if ( $basename === $active_plugin_basename ) { $filename = basename( $basename ); $new_basename = plugin_basename( trailingslashit( $this->_fs->is_premium() ? $this->_fs->get_premium_slug() : $this->_fs->get_slug() ) . $filename ); if ( file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $new_basename ) ) ) { $active_plugins_basenames[ $key ] = $new_basename; update_option( 'active_plugins', $active_plugins_basenames ); } break; } } return $response; } function install_and_activate_plugin( $plugin_id = false ) { if ( ! empty( $plugin_id ) && ! FS_Plugin::is_valid_id( $plugin_id ) ) { return array( 'message' => $this->_fs->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ), 'code' => 'invalid_module_id', ); } $is_addon = false; if ( FS_Plugin::is_valid_id( $plugin_id ) && $plugin_id != $this->_fs->get_id() ) { $addon = $this->_fs->get_addon( $plugin_id ); if ( ! is_object( $addon ) ) { return array( 'message' => $this->_fs->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ), 'code' => 'invalid_module_id', ); } $slug = $addon->slug; $premium_slug = $addon->premium_slug; $title = $addon->title . ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' ); $is_addon = true; } else { $slug = $this->_fs->get_slug(); $premium_slug = $this->_fs->get_premium_slug(); $title = $this->_fs->get_plugin_title() . ( $this->_fs->is_addon() ? ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' ) : '' ); } if ( $this->is_premium_plugin_active( $plugin_id ) ) { return array( 'message' => $is_addon ? $this->_fs->get_text_inline( 'Premium add-on version already installed.', 'auto-install-error-premium-addon-activated' ) : $this->_fs->get_text_inline( 'Premium version already active.', 'auto-install-error-premium-activated' ), 'code' => 'premium_installed', ); } $latest_version = $this->get_latest_download_details( $plugin_id, false, false ); $target_folder = $premium_slug; $extra = array(); $extra['slug'] = $target_folder; $source = $latest_version->url; $api = null; $install_url = add_query_arg( array( 'action' => 'install-plugin', 'plugin' => urlencode( $slug ), ), 'update.php' ); if ( ! class_exists( 'Plugin_Upgrader', false ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; } $skin_args = array( 'type' => 'web', 'title' => sprintf( $this->_fs->get_text_inline( 'Installing plugin: %s', 'installing-plugin-x' ), $title ), 'url' => esc_url_raw( $install_url ), 'nonce' => 'install-plugin_' . $slug, 'plugin' => '', 'api' => $api, 'extra' => $extra, ); $skin = new WP_Ajax_Upgrader_Skin( $skin_args ); $upgrader = new Plugin_Upgrader( $skin ); add_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1, 3 ); $install_result = $upgrader->install( $source ); remove_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1 ); if ( is_wp_error( $install_result ) ) { return array( 'message' => $install_result->get_error_message(), 'code' => $install_result->get_error_code(), ); } elseif ( is_wp_error( $skin->result ) ) { return array( 'message' => $skin->result->get_error_message(), 'code' => $skin->result->get_error_code(), ); } elseif ( $skin->get_errors()->get_error_code() ) { return array( 'message' => $skin->get_error_messages(), 'code' => 'unknown', ); } elseif ( is_null( $install_result ) ) { global $wp_filesystem; $error_code = 'unable_to_connect_to_filesystem'; $error_message = $this->_fs->get_text_inline( 'Unable to connect to the filesystem. Please confirm your credentials.' ); if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) { $error_message = $wp_filesystem->errors->get_error_message(); } return array( 'message' => $error_message, 'code' => $error_code, ); } $plugin_activate = $upgrader->plugin_info(); $activation_result = $this->try_activate_plugin( $plugin_activate ); if ( is_wp_error( $activation_result ) ) { return array( 'message' => $activation_result->get_error_message(), 'code' => $activation_result->get_error_code(), ); } return $skin->get_upgrade_messages(); } protected function try_activate_plugin( $file_path ) { $activate = activate_plugin( $file_path, '', $this->_fs->is_network_active() ); return is_wp_error( $activate ) ? $activate : true; } private function is_premium_plugin_active( $plugin_id = false ) { if ( $plugin_id != $this->_fs->get_id() ) { return $this->_fs->is_addon_activated( $plugin_id, true ); } return is_plugin_active( $this->_fs->premium_plugin_basename() ); } static function _store_basename_for_source_adjustment( $response, $hook_extra ) { if ( isset( $hook_extra['plugin'] ) ) { self::$_upgrade_basename = $hook_extra['plugin']; } else if ( isset( $hook_extra['theme'] ) ) { self::$_upgrade_basename = $hook_extra['theme']; } else { self::$_upgrade_basename = null; } return $response; } static function _maybe_adjust_source_dir( $source, $remote_source, $upgrader ) { if ( ! is_object( $GLOBALS['wp_filesystem'] ) ) { return $source; } $basename = self::$_upgrade_basename; $is_theme = false; if ( isset( $upgrader->skin->options['extra'] ) ) { $desired_slug = $upgrader->skin->options['extra']['slug']; } else if ( ! empty( $basename ) ) { $is_theme = ( ! fs_ends_with( $basename, '.php' ) ); $desired_slug = ( ! $is_theme ) ? dirname( $basename ) : $basename; } else { return $source; } if ( is_multisite() ) { } else if ( ! empty( $basename ) ) { $fs = Freemius::get_instance_by_file( $basename, $is_theme ? WP_FS__MODULE_TYPE_THEME : WP_FS__MODULE_TYPE_PLUGIN ); if ( ! is_object( $fs ) ) { return $source; } } $subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) ); if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) { $from_path = untrailingslashit( $source ); $to_path = trailingslashit( $remote_source ) . $desired_slug; if ( true === $GLOBALS['wp_filesystem']->move( $from_path, $to_path ) ) { return trailingslashit( $to_path ); } return new WP_Error( 'rename_failed', fs_text_inline( 'The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'module-package-rename-failure' ), array( 'found' => $subdir_name, 'expected' => $desired_slug ) ); } return $source; } } 