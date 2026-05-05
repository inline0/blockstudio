/**
 * Seeds app databases with example data.
 *
 * Usage: node seed.mjs
 * Requires: wp-env running on port 8879
 */

const BASE = 'http://localhost:8879/wp-json/blockstudio/v1/db';

async function getHeaders() {
	const html = await fetch( 'http://localhost:8879/' ).then( r => r.text() );
	const nonce = html.match( /X-WP-Nonce":"([^"]+)"/ )?.[ 1 ]
		|| html.match( /wpApiSettings[^}]*"nonce":"([^"]+)"/ )?.[ 1 ];
	const token = html.match( /bs\._token="([^"]+)"/ )?.[ 1 ]
		|| html.match( /X-BS-Token":"([^"]+)"/ )?.[ 1 ];

	return {
		'Content-Type': 'application/json',
		...( nonce ? { 'X-WP-Nonce': nonce } : {} ),
		...( token ? { 'X-BS-Token': token } : {} ),
	};
}

async function clearAll( slug, headers ) {
	const items = await fetch( `${ BASE }/${ slug }/default`, { headers } ).then( r => r.json() );
	if ( ! Array.isArray( items ) ) return;
	for ( const item of items ) {
		await fetch( `${ BASE }/${ slug }/default/${ item.id }`, { method: 'DELETE', headers } );
	}
}

async function create( slug, data, headers ) {
	return fetch( `${ BASE }/${ slug }/default`, {
		method: 'POST',
		headers,
		body: JSON.stringify( data ),
	} ).then( r => r.json() );
}

async function seed() {
	console.log( 'Fetching auth tokens...' );
	const headers = await getHeaders();

	// Todo app
	console.log( 'Clearing todos...' );
	await clearAll( 'app-todo', headers );

	console.log( 'Seeding todos...' );
	const todos = [
		{ text: 'Buy groceries', done: false },
		{ text: 'Write documentation', done: false },
		{ text: 'Review pull request', done: true },
		{ text: 'Deploy to staging', done: false },
		{ text: 'Fix login bug', done: true },
	];

	for ( const todo of todos ) {
		const result = await create( 'app-todo', todo, headers );
		if ( result.text ) {
			console.log( `  + ${ result.text }${ result.done ? ' (done)' : '' }` );
		} else {
			console.log( '  ! Error:', JSON.stringify( result ) );
		}
	}

	console.log( '\nDone. Visit http://localhost:8879/todo-test/' );
}

seed().catch( console.error );
