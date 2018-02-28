<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'pigpen');

/** MySQL database username */
define('DB_USER', 'homestead');

/** MySQL database password */
define('DB_PASSWORD', 'secret');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '}<g?UcT/;)dKQtt9|7k1a$cun~,t(ZCHBqt$|2S0ANQ.dFyht8+N!DWy5^Hja$0s');
define('SECURE_AUTH_KEY',  ' u=Bpb:TLjVu,IV?3h y3^>Dj73.7wPF-:=|W,oT~>ArB+WK@F62qxP0*1p<]O<s');
define('LOGGED_IN_KEY',    '^~?kf<mUyTzx-`niR@IbT<g>zw 7/ScNIU+[OHnRcob_O?[6y%2e=(-a!L!QNK#!');
define('NONCE_KEY',        '9;WM1TB+Gid}H]%KuR~n2F_txq}#y0;4Of0)#p[.&ZK U.H<9:$X2: #1N`I(J:j');
define('AUTH_SALT',        'bAcx[3t=:R3@a99UrA@cHI>56;`MeK+jk},.<ohS]o7S3y(|l;c-$/Rn>%(f+kSf');
define('SECURE_AUTH_SALT', 'sB#RILzK*uLR*v*r+J*F4Dv:(IpMPn)[U3FW,y4#v-NZ@LXZ;LbeL;K`SVQbl7:B');
define('LOGGED_IN_SALT',   '60Uxt2{hI[=?,W}<1A72 6$6N~ZQ5vsOE3sW.WLW.OvyBGtn<Y]kajjN/ 5i2X /');
define('NONCE_SALT',       '<~taca0,ooyxJ*@Bk^&My7aF (TeXTe>{h7e*`Lj$hlwZr%;XYp!Ln6X(gSxTI;l');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
