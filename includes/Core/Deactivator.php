<?php
namespace Kresuber\POS_Pro\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

class Deactivator {
	public static function deactivate() {
		flush_rewrite_rules();
	}
}