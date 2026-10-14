<?php
/**
 * Builds the disguised asset folder: copies this plugin's (and its add-ons')
 * JS/CSS into a randomly named folder under wp_upload_dir(), so ad blockers
 * pattern-matching on "advanced-ads" in a URL can't find them.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Builder class.
 */
class Builder {

	/**
	 * Directory names never recursed into.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_DIRS = [ 'vendor', 'lib', 'admin', 'node_modules', 'packages', 'languages', 'src', 'tests', 'test', 'fancybox' ];

	/**
	 * File extensions considered disguisable assets.
	 *
	 * @var string[]
	 */
	private const ASSET_EXTENSIONS = [ 'css', 'js', 'png', 'gif' ];

	/**
	 * Build-tooling filenames never disguised — never enqueued in a browser,
	 * so an ad blocker can never intercept them.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_FILES = [ 'webpack.mix.js', 'webpack.mix.local.js' ];

	/**
	 * Directory and file names never renamed — some bundled CSS
	 * references sibling assets by relative URL, which breaks if the
	 * folder/file it's relative to gets a random name.
	 *
	 * @var string[]
	 */
	private const UNRENAMED = [ 'public', 'assets', 'js', 'css', 'advanced.js' ];

	/**
	 * Substrings EasyList-style filters match in URLs. Generated folder/file
	 * names must not contain these (case-insensitive). 'ad' also covers ads,
	 * advert, adsense, doubleclick, etc.
	 *
	 * @var string[]
	 */
	private const BLOCKED_NAME_TOKENS = [ 'ad', 'banner', 'click', 'pop', 'track', 'pixel', 'sponsor', 'gpt', 'prebid', 'pub' ];

	/**
	 * Rebuild (or incrementally update) the disguised asset folder end-to-end:
	 * decides the folder name, copies the assets, deletes the old folder on a
	 * rename, then persists folder_name and lookup_table together in a single
	 * call. Nothing is written until the copy fully succeeds — a request that
	 * dies mid-copy leaves whatever was already stored (a prior successful
	 * build, or nothing at all) completely untouched, so readiness is simply
	 * "is folder_name set": it's never persisted pointing at an incomplete copy.
	 *
	 * @param bool $rename True: assign a fresh folder name and copy every
	 *                     current asset, not just the diff — the old
	 *                     create_dummy_plugin() copied only new/changed
	 *                     files even on a rename, silently losing every
	 *                     unchanged file from the freshly-wiped folder.
	 *                     False (default): incrementally copy only new/
	 *                     changed assets, reusing the existing folder and names.
	 *
	 * @return true|\WP_Error
	 */
	public function build( bool $rename = false ) {
		$old_folder_name = wp_advads_get_setting( 'folder_name', 'adblocker' ) ?? '';
		$lookup_table    = wp_advads_get_setting( 'lookup_table', 'adblocker' ) ?? [];
		$rename          = $rename || ! $this->is_safe_folder_name( (string) $old_folder_name ) || $this->is_blockable_name( (string) $old_folder_name );
		$folder_name     = $rename ? $this->generate_name() : $old_folder_name;

		if ( $rename ) {
			$lookup_table = [];
		}

		$current = $this->discover_assets();
		$assets  = $rename ? $current : $this->changed_assets( $current, $lookup_table, $folder_name );

		$result = $this->copy_assets( $folder_name, $assets, $lookup_table, $rename );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $rename ) {
			$result = $this->prune_missing( $current, $result, $folder_name );
		}

		if ( $rename && '' !== $old_folder_name && $old_folder_name !== $folder_name ) {
			$this->delete_folder( $old_folder_name );
		}

		wp_advads()->settings_registry->store->replace_keys(
			'adblocker',
			[
				'folder_name'  => $folder_name,
				'lookup_table' => $result,
			]
		);

		return true;
	}

	/**
	 * A random, filesystem- and URL-safe name — for a folder, or (with
	 * $extension) a file.
	 *
	 * @param string $extension Optional file extension, without the dot.
	 *
	 * @return string
	 */
	public function generate_name( string $extension = '' ): string {
		$tries = 0;

		do {
			$name = wp_generate_password( 12, false, false );
		} while ( $this->is_blockable_name( $name ) && ++$tries < 32 );

		return '' === $extension ? $name : $name . '.' . $extension;
	}

	/**
	 * Whether $folder_name is a single alphanumeric path segment — never `../` or slashes.
	 *
	 * @param string $folder_name Candidate folder name under uploads.
	 *
	 * @return bool
	 */
	private function is_safe_folder_name( string $folder_name ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9]+$/', $folder_name );
	}

	/**
	 * Whether a generated name would be caught by common ad-blocker URL filters.
	 *
	 * @param string $name Folder or file stem (no path separators).
	 *
	 * @return bool
	 */
	private function is_blockable_name( string $name ): bool {
		$lower = strtolower( $name );

		foreach ( self::BLOCKED_NAME_TOKENS as $token ) {
			if ( str_contains( $lower, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every current asset file across this plugin and every advanced-ads*
	 * add-on plugin, recursively. A full snapshot, not a diff — the caller
	 * compares this against a stored lookup table to decide what's new.
	 *
	 * @param string[]|null $plugin_dirs Directories to scan — defaults to every
	 *                                   advanced-ads* plugin dir. Overridable for tests.
	 *
	 * @return array<string, int> Absolute file path => last-modified time.
	 */
	public function discover_assets( ?array $plugin_dirs = null ): array {
		if ( ! isset( $plugin_dirs ) ) {
			$glob_result = glob( trailingslashit( WP_PLUGIN_DIR ) . 'advanced-ads*', GLOB_ONLYDIR );
			$plugin_dirs = $glob_result ? $glob_result : [];
		}

		$assets = [];

		foreach ( $plugin_dirs as $plugin_dir ) {
			$assets += $this->scan_directory( $plugin_dir );
		}

		return $assets;
	}

	/**
	 * Recursively collect asset files under $dir, skipping EXCLUDED_DIRS.
	 *
	 * @param string $dir Directory to scan.
	 *
	 * @return array<string, int> Absolute file path => last-modified time.
	 */
	private function scan_directory( string $dir ): array {
		$assets = [];

		$glob_result = glob( trailingslashit( $dir ) . '*' );
		$glob_result = $glob_result ? $glob_result : [];

		foreach ( $glob_result as $path ) {
			if ( is_dir( $path ) ) {
				if ( ! in_array( basename( $path ), self::EXCLUDED_DIRS, true ) ) {
					$assets += $this->scan_directory( $path );
				}
				continue;
			}

			$filename = basename( $path );

			if ( is_file( $path )
				&& in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), self::ASSET_EXTENSIONS, true )
				&& ! str_ends_with( $filename, 'config.js' )
				&& ! in_array( $filename, self::EXCLUDED_FILES, true )
			) {
				$assets[ $path ] = filemtime( $path );
			}
		}

		return $assets;
	}

	/**
	 * Copy $assets into $folder_name under wp_upload_dir(), renaming .js/.css
	 * files and their containing directories to random names, and merge the
	 * result into $lookup_table.
	 *
	 * @param string                                         $folder_name  Destination folder name under wp_upload_dir()['basedir'].
	 * @param array<string, int>                             $assets       Absolute file path => mtime — only the files to (re)copy.
	 * @param array<string, array{path: string, mtime: int}> $lookup_table Stored lookup table to merge into. In $rename mode, only
	 *                                                                     used to seed already-assigned random names are ignored (renamed fresh).
	 * @param bool                                           $rename      True: wipe the destination folder and assign every name fresh.
	 *
	 * @return array<string, array{path: string, mtime: int}>|\WP_Error New lookup table, or WP_Error on failure.
	 */
	public function copy_assets( string $folder_name, array $assets, array $lookup_table, bool $rename ) {
		$wp_filesystem = WordPress::get_filesystem();

		if ( ! $wp_filesystem ) {
			return new \WP_Error(
				'advads_adblocker_fs',
				__( 'Unable to access the filesystem.', 'advanced-ads' )
			);
		}

		$destination = trailingslashit( wp_upload_dir()['basedir'] ) . $folder_name;

		if ( $rename ) {
			if ( $wp_filesystem->exists( $destination ) && ! $wp_filesystem->rmdir( trailingslashit( $destination ), true ) ) {
				return new \WP_Error(
					'advads_adblocker_rmdir',
					/* translators: %s: directory path */
					sprintf( __( 'Unable to remove the "%s" directory.', 'advanced-ads' ), $destination )
				);
			}

			$lookup_table = [];
			$renamed      = [];
		} else {
			$renamed = $this->renamed_segments( $lookup_table );
		}

		foreach ( $assets as $file => $mtime ) {
			$relative     = str_replace( WP_PLUGIN_DIR, '', $file );
			$new_relative = $this->rename_path( $relative, $renamed );
			$new_absolute = trailingslashit( $destination ) . $new_relative;

			if ( ! wp_mkdir_p( dirname( $new_absolute ) ) ) {
				return new \WP_Error(
					'advads_adblocker_mkdir',
					/* translators: %s: directory path */
					sprintf( __( 'Unable to create the "%s" directory.', 'advanced-ads' ), dirname( $new_absolute ) )
				);
			}

			if ( ! $wp_filesystem->copy( $file, $new_absolute, true, FS_CHMOD_FILE ) ) {
				return new \WP_Error(
					'advads_adblocker_copy',
					/* translators: %s: directory path */
					sprintf( __( 'Unable to copy assets to the "%s" directory.', 'advanced-ads' ), $destination )
				);
			}

			$lookup_table[ $relative ] = [
				'path'  => $new_relative,
				'mtime' => $mtime,
			];
		}

		return $lookup_table;
	}

	/**
	 * Rebuild the "original segment => already-assigned random name" map
	 * from a stored lookup table, so an incremental copy reuses the same
	 * random directory/file names for segments it has already renamed.
	 *
	 * @param array<string, array{path: string, mtime: int}> $lookup_table Stored lookup table.
	 *
	 * @return array<string, string> Original path segment => random replacement.
	 */
	private function renamed_segments( array $lookup_table ): array {
		$renamed = [];

		foreach ( $lookup_table as $original => $entry ) {
			$original_parts = explode( '/', trim( $original, '/' ) );
			$new_parts      = explode( '/', trim( $entry['path'], '/' ) );

			foreach ( $original_parts as $i => $part ) {
				if ( isset( $new_parts[ $i ] ) ) {
					$renamed[ $part ] = $new_parts[ $i ];
				}
			}
		}

		return $renamed;
	}

	/**
	 * Rename a relative asset path's directory segments, and (for .js/.css
	 * only) its filename, to random names — reusing $renamed where a
	 * segment already has one, generating and recording a new one
	 * otherwise. A segment in UNRENAMED is left as-is.
	 *
	 * @param string                $relative Original path, relative to WP_PLUGIN_DIR.
	 * @param array<string, string> $renamed  Original segment => random replacement, updated in place.
	 *
	 * @return string
	 */
	private function rename_path( string $relative, array &$renamed ): string {
		$parts     = explode( '/', trim( $relative, '/' ) );
		$filename  = array_pop( $parts );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// A foreach, not array_map(fn(...)): an arrow function captures
		// $renamed by value, so a name generated for one directory segment
		// wouldn't be visible when renaming the next one, let alone back in
		// copy_assets() for the next file — by-reference has to stay real
		// through the whole chain for reuse to work.
		$new_parts = [];
		foreach ( $parts as $part ) {
			$new_parts[] = $this->rename_segment( $part, $renamed );
		}

		$new_filename = in_array( $extension, [ 'js', 'css' ], true )
			? $this->rename_segment( $filename, $renamed, $extension )
			: $filename;

		return implode( '/', [ ...$new_parts, $new_filename ] );
	}

	/**
	 * Rename a directory or file name to a random name.
	 *
	 * @param string                $segment   Original directory or file name.
	 * @param array<string, string> $renamed   Original segment => random replacement, updated in place.
	 * @param string                $extension File extension for a filename segment, '' for a directory.
	 *
	 * @return string
	 */
	private function rename_segment( string $segment, array &$renamed, string $extension = '' ): string {
		if ( in_array( $segment, self::UNRENAMED, true ) ) {
			return $segment;
		}

		if ( ! isset( $renamed[ $segment ] ) ) {
			$renamed[ $segment ] = $this->generate_name( $extension );
		}

		return $renamed[ $segment ];
	}

	/**
	 * Which of $current's assets are new, changed, or whose destination copy
	 * is missing from disk — relative to $lookup_table.
	 *
	 * @param array<string, int>                             $current      Full current asset snapshot (see discover_assets()).
	 * @param array<string, array{path: string, mtime: int}> $lookup_table Stored lookup table.
	 * @param string                                         $folder_name  Destination folder name under wp_upload_dir()['basedir'].
	 *
	 * @return array<string, int>
	 */
	private function changed_assets( array $current, array $lookup_table, string $folder_name ): array {
		$destination = trailingslashit( wp_upload_dir()['basedir'] ) . trailingslashit( $folder_name );
		$changed     = [];

		foreach ( $current as $file => $mtime ) {
			$relative = str_replace( WP_PLUGIN_DIR, '', $file );
			$entry    = $lookup_table[ $relative ] ?? null;

			if ( ! $entry || $entry['mtime'] !== $mtime || ! file_exists( $destination . $entry['path'] ) ) {
				$changed[ $file ] = $mtime;
			}
		}

		return $changed;
	}

	/**
	 * Drop lookup_table keys whose source file is gone, and delete the dest copy.
	 *
	 * @param array<string, int>                             $current      Full current asset snapshot (see discover_assets()).
	 * @param array<string, array{path: string, mtime: int}> $lookup_table Stored lookup table.
	 * @param string                                         $folder_name  Destination folder name under wp_upload_dir()['basedir'].
	 *
	 * @return array<string, array{path: string, mtime: int}>
	 */
	private function prune_missing( array $current, array $lookup_table, string $folder_name ): array {
		$wp_filesystem = WordPress::get_filesystem();
		if ( ! $wp_filesystem ) {
			return $lookup_table;
		}

		$alive = [];
		foreach ( array_keys( $current ) as $file ) {
			$alive[ str_replace( WP_PLUGIN_DIR, '', $file ) ] = true;
		}

		$destination = trailingslashit( wp_upload_dir()['basedir'] ) . trailingslashit( $folder_name );

		foreach ( $lookup_table as $relative => $entry ) {
			if ( isset( $alive[ $relative ] ) ) {
				continue;
			}

			$path = $entry['path'] ?? '';
			if ( '' !== $path && ! str_contains( $path, '..' ) ) {
				$dest = $destination . $path;
				if ( $wp_filesystem->exists( $dest ) ) {
					$wp_filesystem->delete( $dest );
				}
			}

			unset( $lookup_table[ $relative ] );
		}

		return $lookup_table;
	}

	/**
	 * Delete a previous disguise folder under wp_upload_dir().
	 *
	 * @param string $folder_name Folder name under wp_upload_dir()['basedir'].
	 *
	 * @return void
	 */
	private function delete_folder( string $folder_name ): void {
		if ( ! $this->is_safe_folder_name( $folder_name ) ) {
			return;
		}

		$wp_filesystem = WordPress::get_filesystem();
		$path          = trailingslashit( wp_upload_dir()['basedir'] ) . $folder_name;

		if ( $wp_filesystem && $wp_filesystem->exists( $path ) ) {
			$wp_filesystem->rmdir( trailingslashit( $path ), true );
		}
	}
}
