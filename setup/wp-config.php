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

// ** MySQL settings ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'root' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '8uCjxOkUeE0Ne5nA0d5Kn2OVpBIFDe3up8k85fJLs8YxV34MCqLU5EB/7SjwEq+CdTcyl8xa9rxOHab31/GAiQ==');
define('SECURE_AUTH_KEY',  '9NVyV1xcdbnCO5gs5kTJG9ksp8sayXgjW5J1mrFExDQMSGV9AGrFUwcWSk64CoZR1XPnGSGFSYhYSgsj2K3oqw==');
define('LOGGED_IN_KEY',    'JNbPJ7o6Ifwpszr7naVObUbNbcfpmEbGE1Y2EG9+ptrNYMNXqCJ1CjGJnPQiHqgKgBO73fW6FImGlfhcSPTXEQ==');
define('NONCE_KEY',        'Zdka+Bo9RCWZTwNNoVw5O6kGuo/GmjPdJ5TqO4sCozmwWaROD0SaBXEN+fMG1Rf7cyQlciVTMz5AwZ3KpTVdaw==');
define('AUTH_SALT',        'vjaWgM2RyRwxZLXO1uS1QV/l5TUFV7tyTuhT4FIr0n0oIM51g4WQq717LpBZMZ86akO1O9Qdp1KN0FZPSXQPiQ==');
define('SECURE_AUTH_SALT', 'xF8mh58cPEdc8dRwsy7scvZnnpK9v2Qq1e9RePAOUB4oWfVE27lvrxKxd6RV9zYtYeR0Gv9RIvGLuxDSHb8tyg==');
define('LOGGED_IN_SALT',   'ScAWg0/YV2WFpoAabLn2jreMjSoAvvASqZGMHMJzBVYV1nTE+JBmuDh7NgnoOkiCcgxnVynLt+dPy7X+U90c3Q==');
define('NONCE_SALT',       'lt8W9Fv8UrTefGPKuP1cogWj1cZJkNUjSxwmgSzYS1v1MMJntZKSEaj4KyFqSJXASzmOZnSEaWCv6P0cxCcKYg==');

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';




/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) )
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
