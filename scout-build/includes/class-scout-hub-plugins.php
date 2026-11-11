<?php
/**
 * Outside plugins: every active plugin that is not a Scout plugin and not
 * Gravity Forms, for Site status (Site Standard rules 8 and 34).
 *
 * Gravity Forms is the one outside plugin every Scout site may run. Any other
 * needs a dated entry in the site's decisions file naming the job, the owner
 * and the version watch. Build cannot read that file, so the theme's
 * scout-setup.json carries the same list as "approved_plugins" (read by
 * Scout_Hub_Setup::approved_plugins()). A plugin on that list shows as
 * approved; any other shows as needing a decision.
 *
 * WPMU DEV was retired from every Scout site on 2026-11-02, so an active WPMU
 * DEV plugin gets its own red row, and the approved list never clears it.
 *
 * Reads only the plugin list WordPress already has. Admin screens only;
 * nothing here reaches visitors.
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Hub_Plugins {

	/** The repos Scout plugins update from (the same list as Scout_Hub_Log::REPOS). */
	const SCOUT_REPOS = array( 'scout-media-raleigh/scout-plugins', 'joyrmac/scout-plugins' );

	/** Hosts in a plugin's Author URI or Plugin URI that mean Scout made it (companion plugins have no Update URI). */
	const SCOUT_HOSTS = array( 'scoutraleigh.com' );

	/** Hosts that mean Gravity Forms or one of its official add-ons. */
	const GRAVITY_HOSTS = array( 'gravityforms.com', 'rocketgenius.com' );

	/** Hosts that mean WPMU DEV (Incsub is the company behind it). */
	const WPMUDEV_HOSTS = array( 'wpmudev.com', 'wpmudev.org', 'incsub.com' );

	/**
	 * Folder names of WPMU DEV plugins, free and Pro: Dashboard, Defender, Smush,
	 * Hummingbird, Branda, SmartCrawl, Forminator, Hustle, Beehive, Snapshot and
	 * Shipper. Every one also carries WPMU DEV as its author, which is checked too.
	 */
	const WPMUDEV_SLUGS = array(
		'wpmudev-updates',
		'wp-defender',
		'defender-security',
		'wp-smushit',
		'wp-smush-pro',
		'hummingbird-performance',
		'wp-hummingbird',
		'branda-white-labeling',
		'ultimate-branding',
		'smartcrawl-seo',
		'wpmu-dev-seo',
		'forminator',
		'forminator-pro',
		'wordpress-popup',
		'hustle',
		'beehive-analytics',
		'beehive',
		'snapshot',
		'snapshot-backups',
		'shipper',
	);

	/** The two row labels, so Site status can file them under Plugins. */
	public static function labels(): array {
		return array( __( 'Outside plugins', 'scout-build' ), __( 'WPMU DEV', 'scout-build' ) );
	}

	/** A plugin's folder name ("wp-rocket" for wp-rocket/wp-rocket.php, "hello" for hello.php). */
	public static function slug( string $file ): string {
		$dir = dirname( $file );
		return strtolower( '.' === $dir || '' === $dir ? basename( $file, '.php' ) : $dir );
	}

	/**
	 * Which kind of plugin this is: scout, gravity, wpmudev or outside.
	 *
	 * @param string $file The plugin file, as get_plugins() keys it.
	 * @param array  $data The plugin's headers, as get_plugins() returns them.
	 */
	public static function classify( string $file, array $data ): string {
		$slug   = self::slug( $file );
		$update = strtolower( (string) ( $data['UpdateURI'] ?? '' ) );
		$author = strtolower( (string) ( $data['Author'] ?? '' ) );
		$hosts  = array();
		foreach ( array( 'AuthorURI', 'PluginURI' ) as $key ) {
			$host = strtolower( (string) parse_url( (string) ( $data[ $key ] ?? '' ), PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- plain host read, no WordPress needed.
			if ( '' !== $host ) {
				$hosts[] = preg_replace( '/^www\./', '', $host );
			}
		}
		$on = static function ( array $list ) use ( $hosts ): bool {
			foreach ( $hosts as $host ) {
				foreach ( $list as $want ) {
					if ( $host === $want || str_ends_with( $host, '.' . $want ) ) {
						return true;
					}
				}
			}
			return false;
		};

		foreach ( self::SCOUT_REPOS as $repo ) {
			if ( false !== strpos( $update, $repo ) ) {
				return 'scout';
			}
		}
		if ( false !== strpos( $author, 'scout media' ) || $on( self::SCOUT_HOSTS ) ) {
			return 'scout';
		}
		if ( 'gravityforms' === $slug || $on( self::GRAVITY_HOSTS ) || in_array( trim( $author ), array( 'gravity forms', 'rocketgenius' ), true ) ) {
			return 'gravity';
		}
		if ( in_array( $slug, self::WPMUDEV_SLUGS, true ) || $on( self::WPMUDEV_HOSTS ) || preg_match( '/wpmu\s*dev|incsub/', $author ) ) {
			return 'wpmudev';
		}
		return 'outside';
	}

	/**
	 * The Site status rows for a plugin list. No WordPress needed, so the test
	 * can call it straight.
	 *
	 * @param array $plugins  file => headers, as get_plugins() returns them.
	 * @param array $active   The active plugin files.
	 * @param array $approved slug => entry, from Scout_Hub_Setup::approved_plugins().
	 * @return array<int, array> The Outside plugins row, then the WPMU DEV row when one is on.
	 */
	public static function rows( array $plugins, array $active, array $approved ): array {
		$waiting = array();
		$okayed  = array();
		$wpmudev = array();
		foreach ( $plugins as $file => $data ) {
			if ( ! in_array( $file, $active, true ) ) {
				continue;
			}
			$kind = self::classify( (string) $file, (array) $data );
			$name = trim( (string) ( $data['Name'] ?? '' ) );
			$name = '' !== $name ? $name : self::slug( (string) $file );
			if ( 'wpmudev' === $kind ) {
				$wpmudev[] = $name;
			} elseif ( 'outside' === $kind ) {
				if ( isset( $approved[ self::slug( (string) $file ) ] ) ) {
					$okayed[] = $name;
				} else {
					$waiting[] = $name;
				}
			}
		}
		$link = array(
			'link'      => admin_url( 'plugins.php' ),
			'link_text' => __( 'Plugins', 'scout-build' ),
		);

		$out = array();
		if ( ! $waiting && ! $okayed ) {
			$out[] = array(
				'label' => __( 'Outside plugins', 'scout-build' ),
				'value' => __( 'None', 'scout-build' ),
				'state' => 'good',
				'note'  => __( 'Only Scout plugins and Gravity Forms are on.', 'scout-build' ),
			);
		} elseif ( ! $waiting ) {
			$out[] = array(
				'label'   => __( 'Outside plugins', 'scout-build' ),
				/* translators: %d: number of plugins */
				'value'   => sprintf( _n( '%d approved', '%d approved', count( $okayed ), 'scout-build' ), count( $okayed ) ),
				'state'   => 'good',
				'note'    => _n( 'One plugin is not a Scout plugin or Gravity Forms. It is on this site\'s approved list.', 'These plugins are not Scout plugins or Gravity Forms. Each one is on this site\'s approved list.', count( $okayed ), 'scout-build' ),
				'items'   => $okayed,
				/* translators: %s: plugin names */
				'details' => sprintf( __( 'Approved: %s.', 'scout-build' ), implode( ', ', $okayed ) ),
			);
		} else {
			$items = array();
			foreach ( $waiting as $name ) {
				/* translators: %s: plugin name */
				$items[] = sprintf( __( '%s: needs a decision', 'scout-build' ), $name );
			}
			foreach ( $okayed as $name ) {
				/* translators: %s: plugin name */
				$items[] = sprintf( __( '%s: approved', 'scout-build' ), $name );
			}
			/* translators: %s: plugin names */
			$details = sprintf( __( 'Needs a decision: %s.', 'scout-build' ), implode( ', ', $waiting ) );
			if ( $okayed ) {
				/* translators: %s: plugin names */
				$details .= ' ' . sprintf( __( 'Approved: %s.', 'scout-build' ), implode( ', ', $okayed ) );
			}
			$details .= ' ' . __( 'Keep one by adding a dated entry to the site\'s decisions file and its name to approved_plugins in scout-setup.json. Otherwise turn it off.', 'scout-build' );
			$out[]    = array(
				'label'   => __( 'Outside plugins', 'scout-build' ),
				/* translators: %d: number of plugins */
				'value'   => sprintf( _n( '%d needs a decision', '%d need a decision', count( $waiting ), 'scout-build' ), count( $waiting ) ),
				'state'   => 'warn',
				'note'    => _n( 'One plugin is not a Scout plugin or Gravity Forms, and nobody has said yes to it yet. It needs a decision: keep it or turn it off.', 'These plugins are not Scout plugins or Gravity Forms, and nobody has said yes to them yet. Each one needs a decision: keep it or turn it off.', count( $waiting ), 'scout-build' ),
				'items'   => $items,
				'details' => $details,
			) + $link;
		}

		if ( $wpmudev ) {
			$out[] = array(
				'label'   => __( 'WPMU DEV', 'scout-build' ),
				/* translators: %d: number of plugins */
				'value'   => sprintf( _n( '%d plugin on', '%d plugins on', count( $wpmudev ), 'scout-build' ), count( $wpmudev ) ),
				'state'   => 'bad',
				'note'    => __( 'WPMU DEV was retired from every site on Nov 2, 2026. Turn these plugins off and delete them.', 'scout-build' ),
				'items'   => $wpmudev,
				/* translators: %s: plugin names */
				'details' => sprintf( __( 'WPMU DEV is still on: %s. It was retired from every site on Nov 2, 2026. Turn these plugins off and delete them.', 'scout-build' ), implode( ', ', $wpmudev ) ),
			) + $link;
		}
		return $out;
	}

	/** The rows for this site: its active plugins against the theme's approved list. */
	public static function checks(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = (array) get_plugins();
		$active  = array();
		foreach ( array_keys( $plugins ) as $file ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $file;
			}
		}
		$approved = class_exists( 'Scout_Hub_Setup' ) ? Scout_Hub_Setup::approved_plugins() : array();
		return self::rows( $plugins, $active, $approved );
	}
}
