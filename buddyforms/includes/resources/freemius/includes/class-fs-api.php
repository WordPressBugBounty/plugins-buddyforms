<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

 if ( ! defined( 'ABSPATH' ) ) { exit; } class FS_Api { private static $_instances = array(); private static $_options; private static $_cache; private static $_clock_diff; private $_api; private $_slug; private $_logger; private $_sdk_version; private $_url; static function instance( $slug, $scope, $id, $public_key, $is_sandbox, $secret_key = false, $sdk_version = null, $url = null ) { $identifier = md5( $slug . $scope . $id . $public_key . ( is_string( $secret_key ) ? $secret_key : '' ) . json_encode( $is_sandbox ) ); if ( ! isset( self::$_instances[ $identifier ] ) ) { self::_init(); self::$_instances[ $identifier ] = new FS_Api( $slug, $scope, $id, $public_key, $secret_key, $is_sandbox, $sdk_version, $url ); } return self::$_instances[ $identifier ]; } private static function _init() { if ( isset( self::$_options ) ) { return; } if ( ! class_exists( 'Freemius_Api_WordPress' ) ) { require_once WP_FS__DIR_SDK . '/FreemiusWordPress.php'; } self::$_options = FS_Option_Manager::get_manager( WP_FS__OPTIONS_OPTION_NAME, true, true ); self::$_cache = FS_Cache_Manager::get_manager( WP_FS__API_CACHE_OPTION_NAME ); self::$_clock_diff = self::$_options->get_option( 'api_clock_diff', 0 ); Freemius_Api_WordPress::SetClockDiff( self::$_clock_diff ); if ( self::$_options->get_option( 'api_force_http', false ) ) { Freemius_Api_WordPress::SetHttp(); } } private function __construct( $slug, $scope, $id, $public_key, $secret_key, $is_sandbox, $sdk_version, $url ) { $this->_api = new Freemius_Api_WordPress( $scope, $id, $public_key, $secret_key, $is_sandbox ); $this->_slug = $slug; $this->_sdk_version = $sdk_version; $this->_url = $url; $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $slug . '_api', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK ); } private function _sync_clock_diff( $diff = false ) { $this->_logger->entrance(); $new_clock_diff = ( false === $diff ) ? Freemius_Api_WordPress::FindClockDiff() : $diff; if ( $new_clock_diff === self::$_clock_diff ) { return false; } self::$_clock_diff = $new_clock_diff; Freemius_Api_WordPress::SetClockDiff( self::$_clock_diff ); self::$_options->set_option( 'api_clock_diff', self::$_clock_diff, true ); return $new_clock_diff; } private function _call( $path, $method = 'GET', $params = array(), $in_retry = false ) { $this->_logger->entrance( $method . ':' . $path ); $force_http = ( ! $in_retry && self::$_options->get_option( 'api_force_http', false ) ); if ( self::is_temporary_down() ) { $result = $this->get_temporary_unavailable_error(); } else { if ( ! empty( $this->_sdk_version ) ) { if ( false === strpos( $path, 'sdk_version=' ) && ! isset( $params['sdk_version'] ) ) { $path = add_query_arg( 'sdk_version', $this->_sdk_version, $path ); } } if ( ! empty( $this->_url ) ) { if ( false === strpos( $path, 'url=' ) && ! isset( $params['url'] ) ) { $path = add_query_arg( 'url', $this->_url, $path ); } } $result = $this->_api->Api( $path, $method, $params ); if ( ! $in_retry && null !== $result && isset( $result->error ) && isset( $result->error->code ) ) { $retry = false; if ( 'request_expired' === $result->error->code ) { $diff = isset( $result->error->timestamp ) ? ( time() - strtotime( $result->error->timestamp ) ) : false; if ( false !== $this->_sync_clock_diff( $diff ) ) { $retry = true; } } else if ( Freemius_Api_WordPress::IsHttps() && FS_Api::is_ssl_error_response( $result ) ) { $force_http = true; $retry = true; } if ( $retry ) { if ( $force_http ) { $this->toggle_force_http( true ); } $result = $this->_call( $path, $method, $params, true ); } } } if ( self::is_api_error( $result ) ) { if ( $this->_logger->is_on() ) { $this->_logger->api_error( $result ); } if ( $force_http ) { $this->toggle_force_http( false ); } } return $result; } function call( $path, $method = 'GET', $params = array() ) { return $this->_call( $path, $method, $params ); } function get_signed_url( $path ) { return $this->_api->GetSignedUrl( $path ); } function get( $path = '/', $flush = false, $expiration = WP_FS__TIME_24_HOURS_IN_SEC ) { $this->_logger->entrance( $path ); $cache_key = $this->get_cache_key( $path ); if ( WP_FS__DEV_MODE || $this->_api->IsSandbox() ) { $flush = true; } $has_valid_cache = self::$_cache->has_valid( $cache_key, $expiration ); $cached_result = $has_valid_cache ? self::$_cache->get( $cache_key ) : null; if ( $flush || is_null( $cached_result ) ) { $result = $this->call( $path ); if ( ! is_object( $result ) || isset( $result->error ) ) { if ( is_object( $cached_result ) && ! isset( $cached_result->error ) ) { $result = $cached_result; if ( $this->_logger->is_on() ) { $this->_logger->warn( 'Fallback to cached API result: ' . var_export( $cached_result, true ) ); } } else { if ( is_object( $result ) && isset( $result->error->http ) && 404 == $result->error->http ) { $expiration /= 2; } else { return $result; } } } self::$_cache->set( $cache_key, $result, $expiration ); $cached_result = $result; } else { $this->_logger->log( 'Using cached API result.' ); } return $cached_result; } static function remote_request( $url, $remote_args ) { if ( ! class_exists( 'Freemius_Api_WordPress' ) ) { require_once WP_FS__DIR_SDK . '/FreemiusWordPress.php'; } if ( method_exists( 'Freemius_Api_WordPress', 'RemoteRequest' ) ) { return Freemius_Api_WordPress::RemoteRequest( $url, $remote_args ); } $response = wp_remote_request( $url, $remote_args ); if ( is_array( $response ) && ( empty( $response['headers'] ) || empty( $response['headers']['x-api-server'] ) ) ) { $response = new WP_Error( 'api_blocked', htmlentities( $response['body'] ) ); } return $response; } function is_cached( $path, $method = 'GET', $params = array() ) { $cache_key = $this->get_cache_key( $path, $method, $params ); return self::$_cache->has_valid( $cache_key ); } function purge_cache( $path, $method = 'GET', $params = array() ) { $this->_logger->entrance( "{$method}:{$path}" ); $cache_key = $this->get_cache_key( $path, $method, $params ); self::$_cache->purge( $cache_key ); } function update_cache_expiration( $path, $expiration = WP_FS__TIME_24_HOURS_IN_SEC, $method = 'GET', $params = array() ) { $this->_logger->entrance( "{$method}:{$path}:{$expiration}" ); $cache_key = $this->get_cache_key( $path, $method, $params ); self::$_cache->update_expiration( $cache_key, $expiration ); } private function get_cache_key( $path, $method = 'GET', $params = array() ) { $canonized = $this->_api->CanonizePath( $path ); return strtolower( $method . ':' . $canonized ) . ( ! empty( $params ) ? '#' . md5( json_encode( $params ) ) : '' ); } private function toggle_force_http( $is_http ) { self::$_options->set_option( 'api_force_http', $is_http, true ); if ( $is_http ) { Freemius_Api_WordPress::SetHttp(); } else if ( method_exists( 'Freemius_Api_WordPress', 'SetHttps' ) ) { Freemius_Api_WordPress::SetHttps(); } } static function is_blocked( $response ) { return ( self::is_api_error_object( $response, true ) && isset( $response->error->code ) && 'api_blocked' === $response->error->code ); } static function is_temporary_down() { self::_init(); $test = self::$_cache->get_valid( 'ping_test', null ); return ( false === $test ); } private function get_temporary_unavailable_error() { return (object) array( 'error' => (object) array( 'type' => 'TemporaryUnavailable', 'message' => 'API is temporary unavailable, please retry in ' . ( self::$_cache->get_record_expiration( 'ping_test' ) - WP_FS__SCRIPT_START_TIME ) . ' sec.', 'code' => 'temporary_unavailable', 'http' => 503 ) ); } private static function should_try_with_http( $result ) { if ( ! Freemius_Api_WordPress::IsHttps() ) { return false; } return ( ! is_object( $result ) || ! isset( $result->error ) || ! isset( $result->error->code ) || ! in_array( $result->error->code, array( 'curl_missing', 'cloudflare_ddos_protection', 'maintenance_mode', 'squid_cache_block', 'too_many_requests', ) ) ); } function get_url( $path = '' ) { return Freemius_Api_WordPress::GetUrl( $path, $this->_api->IsSandbox() ); } static function clear_cache() { self::_init(); self::$_cache = FS_Cache_Manager::get_manager( WP_FS__API_CACHE_OPTION_NAME ); self::$_cache->clear(); } static function clear_force_http_flag() { self::$_options->unset_option( 'api_force_http' ); } static function is_api_error( $result ) { return ( is_object( $result ) && isset( $result->error ) ) || is_string( $result ); } static function is_api_error_object( $result, $ignore_message = false ) { return ( is_object( $result ) && isset( $result->error ) && ( $ignore_message || isset( $result->error->message ) ) ); } static function is_ssl_error_response( $response ) { $http_error = null; if ( $response instanceof WP_Error ) { if ( isset( $response->errors ) && isset( $response->errors['http_request_failed'] ) ) { $http_error = strtolower( $response->errors['http_request_failed'][0] ); } } else if ( self::is_api_error_object( $response ) && ! empty( $response->error->message ) ) { $http_error = $response->error->message; } return ( ! empty( $http_error ) && ( false !== strpos( $http_error, 'curl error 35' ) || ( false === strpos( $http_error, '</html>' ) && false !== strpos( $http_error, 'ssl' ) ) ) ); } static function is_api_result_object( $result, $required_property = null ) { return ( is_object( $result ) && ! isset( $result->error ) && ( empty( $required_property ) || isset( $result->{$required_property} ) ) ); } static function is_api_result_entity( $result ) { return self::is_api_result_object( $result, 'id' ) && FS_Entity::is_valid_id( $result->id ); } static function get_error_code( $result ) { if ( is_object( $result ) && isset( $result->error ) && is_object( $result->error ) && ! empty( $result->error->code ) ) { return $result->error->code; } return ''; } }