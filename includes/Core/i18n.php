<?php
namespace Kresuber\POS_Pro\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

class i18n {
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'kresuber-pos-pro',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}