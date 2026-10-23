#!/usr/bin/env node
/**
 * Synchronizes the plugin version across all canonical locations.
 *
 * Source of truth: package.json "version".
 * Target locations:
 *   - like-json.php header (Version: x.y.z)
 *   - like-json.php LIKE_JSON_VERSION constant
 *   - readme.txt (Stable tag + "= x.y.z =" changelog top entry)
 *
 * Fails with a non-zero exit code if the versions are inconsistent,
 * per the RRZE SPEC ("A build or release MUST fail if inconsistent
 * versions are detected").
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const pkg = JSON.parse( fs.readFileSync( path.join( root, 'package.json' ), 'utf8' ) );
const version = pkg.version;

if ( !/^\d+\.\d+\.\d+$/.test( version ) ) {
	console.error( `Invalid version in package.json: ${version}` );
	process.exit( 1 );
}

const main = path.join( root, 'like-json.php' );
const readme = path.join( root, 'readme.txt' );

let mainContent = fs.readFileSync( main, 'utf8' );
let readmeContent = fs.readFileSync( readme, 'utf8' );

// --- Validate current state ---
const headerVersion = mainContent.match( /^\s*\*\s*Version:\s*([0-9.]+)/m );
const constVersion = mainContent.match( /define\( 'LIKE_JSON_VERSION', '([^']+)' \)/ );
const stableTag = readmeContent.match( /^Stable tag:\s*([0-9.]+)/m );
const changelog = readmeContent.match( /^=\s*([0-9.]+)\s*=/m );

const found = {
	'header Version': headerVersion ? headerVersion[ 1 ] : null,
	'LIKE_JSON_VERSION': constVersion ? constVersion[ 1 ] : null,
	'Stable tag': stableTag ? stableTag[ 1 ] : null,
	'changelog top': changelog ? changelog[ 1 ] : null,
};

const mismatches = Object.entries( found ).filter( ( [ , v ] ) => v !== version );
if ( mismatches.length > 0 ) {
	console.error( `Version mismatch detected. Expected "${version}" everywhere.` );
	for ( const [ name, v ] of Object.entries( found ) ) {
		const ok = v === version ? 'OK' : 'MISMATCH';
		console.error( `  [${ok}] ${name}: ${v}` );
	}
	console.error( '\nRun: npm run sync:version -- --fix' );
	process.exit( 1 );
}

// --- Fix mode ---
if ( process.argv.includes( '--fix' ) ) {
	mainContent = mainContent.replace( /^(\s*\*\s*Version:\s*)[0-9.]+/m, `$1${version}` );
	mainContent = mainContent.replace( /(define\( 'LIKE_JSON_VERSION', ')[^']+(' \))/m, `$1${version}$2` );
	readmeContent = readmeContent.replace( /^(Stable tag:\s*)[0-9.]+/m, `$1${version}` );
	// Update the top changelog entry version (if it matches the OLD version).
	readmeContent = readmeContent.replace( /^= [0-9.]+ =/m, `= ${version} =` );

	fs.writeFileSync( main, mainContent );
	fs.writeFileSync( readme, readmeContent );
	console.log( `Synchronized version ${version} across all locations.` );
} else {
	console.log( `Version ${version} is consistent across all locations.` );
}
