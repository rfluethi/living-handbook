<?php
/**
 * PHP-Scoper configuration for the release build.
 *
 * WordPress loads every plugin into one PHP process, and Composer libraries
 * shipped inside a plugin claim global names. If another plugin ships the same
 * library in a different version and loads first, this plugin gets that one:
 * CommonMark 1.x and 2.x both offer GithubFlavoredMarkdownConverter, and the
 * method the import calls exists in only one of them. The runtime check in
 * MarkdownConverter::available() keeps that from becoming a fatal error, but it
 * does so by switching the import off, which is not a solution, only a fuse.
 *
 * The fix is to give the bundled copies a namespace nobody else will use. Only
 * vendor/ is prefixed, never src/: WordPress classes like WP_Error live in the
 * global namespace and would be prefixed along with everything else, and the
 * plugin's own code would be reformatted on its way into the release. Instead
 * the three places that name a library class ask Support\Vendored for the name
 * this installation has, which is the prefixed one in a release build and the
 * plain one in a development checkout.
 *
 * Used by bin/build.sh. This file does not ship in the plugin zip.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

return array(
	// Chosen to sit under the plugin's own namespace, so it is obvious where a
	// class in a stack trace comes from. The plugin's autoloader is registered
	// for LivingHandbook\ and is asked for these names first: it looks for a
	// file under src/, finds none and returns, and Composer answers.
	'prefix'                  => 'LivingHandbook\\Vendor',

	// Nothing of the plugin's own is scoped, but say so anyway: if this config
	// is ever pointed at the whole plugin by accident, src/ stays untouched.
	'exclude-namespaces'      => array( 'LivingHandbook' ),

	// Global names declared inside the libraries are prefixed as well. That is
	// the point of the exercise: a polyfill or helper function that stays global
	// is exactly the kind of thing that collides with another plugin.
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
);
