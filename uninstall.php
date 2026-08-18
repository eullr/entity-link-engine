<?php
/**
 * Uninstall Entity Link Engine.
 *
 * Removes options, custom tables and post meta created by the plugin.
 *
 * @package EntityLinkEngine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-install.php';
ELE_Install::uninstall();
