<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

 if ( ! defined( 'ABSPATH' ) ) { exit; } class FS_User extends FS_Scope_Entity { public $email; public $first; public $last; public $is_verified; public $customer_id; public $gross; function __construct( $user = false ) { parent::__construct( $user ); } function __wakeup() { if ( property_exists( $this, 'is_beta' ) ) { unset( $this->is_beta ); } } function get_name() { return trim( ucfirst( trim( is_string( $this->first ) ? $this->first : '' ) ) . ' ' . ucfirst( trim( is_string( $this->last ) ? $this->last : '' ) ) ); } function is_verified() { return ( isset( $this->is_verified ) && true === $this->is_verified ); } function is_beta() { return false; } static function get_type() { return 'user'; } }