#!/usr/bin/env node
// Build a Pagefind index from records supplied by the SifterSearch PHP job.
//
// Each record is { url, content } where content is a full HTML document. We
// hand it to Pagefind's addHTMLFile so Pagefind performs its own text
// extraction (respecting data-pagefind-body), exactly as the CLI would when
// crawling a built site.

import { readFileSync } from 'node:fs';
import * as pagefind from 'pagefind';

function arg( name ) {
	const i = process.argv.indexOf( name );
	return i !== -1 ? process.argv[ i + 1 ] : undefined;
}

const recordsPath = arg( '--records' );
const outputPath = arg( '--output' );

if ( !recordsPath || !outputPath ) {
	console.error( 'Usage: build-index.mjs --records <file> --output <dir>' );
	process.exit( 2 );
}

const records = JSON.parse( readFileSync( recordsPath, 'utf8' ) );

const { index, errors } = await pagefind.createIndex();
if ( !index ) {
	console.error( 'Failed to create Pagefind index:', errors );
	process.exit( 1 );
}

for ( const record of records ) {
	await index.addHTMLFile( { url: record.url, content: record.content } );
}

const write = await index.writeFiles( { outputPath } );
await pagefind.close();

if ( write.errors && write.errors.length ) {
	console.error( 'Pagefind write errors:', write.errors );
	process.exit( 1 );
}
