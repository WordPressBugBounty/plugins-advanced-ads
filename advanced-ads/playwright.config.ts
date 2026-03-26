import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from '@playwright/test';

// Load external config file.
let externalConfig = {};
const jsonPath = path.resolve( './dev.config.json' );

if ( fs.existsSync( jsonPath ) ) {
	externalConfig = JSON.parse( fs.readFileSync( jsonPath, 'utf8' ) );
}

export default defineConfig( {
	name: 'Advanced Ads',
	testDir: 'tests/Acceptance',
	use: {
		baseURL: 'http://localhost:8888',
		browserName: 'chromium',
		...externalConfig.use,
	},
	reporter: 'html',
	projects: [
		// ----------------------------------------------------
		// Setup project (creates auth.json for wp-admin login)
		// ----------------------------------------------------
		{
			name: 'setup',
			testMatch: 'tests/Acceptance/fixtures/auth.setup.ts',
		},

		// ----------------------------------------------------
		// Backend (wp-admin) tests
		// ----------------------------------------------------
		{
			name: 'admin',
			testDir: 'tests/Acceptance/Admin',
			use: { storageState: 'auth.json' },
			dependencies: [ 'setup' ],
		},

		{
			name: 'admin-ads',
			testDir: 'tests/Acceptance/Admin/Ads',
			use: { storageState: 'auth.json' },
			dependencies: [ 'setup' ],
		},

		{
			name: 'admin-groups',
			testDir: 'tests/Acceptance/Admin/Groups',
			use: { storageState: 'auth.json' },
			dependencies: [ 'admin-ads' ],
		},

		{
			name: 'admin-placements',
			testDir: 'tests/Acceptance/Admin/Placements',
			use: { storageState: 'auth.json' },
			dependencies: [ 'admin-groups' ],
		},

		// ----------------------------------------------------
		// Frontend tests
		// ----------------------------------------------------
		{
			name: 'frontend',
			testDir: 'tests/Acceptance/Frontend',
			use: { storageState: 'auth.json' },
		},
		{
			name: 'frontend-auth',
			testDir: 'tests/Acceptance/Frontend/Auth',
			use: { storageState: 'auth.json' },
			dependencies: [ 'setup' ],
		},
	],
} );
