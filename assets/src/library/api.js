/**
 * Calls to the crnd/v1 REST API. apiFetch adds the REST nonce by itself; uploads use
 * XMLHttpRequest because fetch() cannot report upload progress.
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const BASE = '/crnd/v1';
const config = window.crndLibrary || {};

const query = ( params ) =>
	new URLSearchParams(
		Object.entries( params ).filter(
			( [ , value ] ) =>
				value !== '' && value !== undefined && value !== null
		)
	).toString();

export const errorMessage = ( error ) =>
	error?.message ||
	__( 'Something went wrong. Please try again.', 'crnube-docs' );

export const getCategories = () => apiFetch( { path: `${ BASE }/categories` } );

export const getYears = () => apiFetch( { path: `${ BASE }/documents/years` } );

export async function getDocuments( params ) {
	const response = await apiFetch( {
		path: `${ BASE }/documents?${ query( params ) }`,
		parse: false,
	} );
	return {
		items: await response.json(),
		total: Number( response.headers.get( 'X-WP-Total' ) ) || 0,
		pages: Number( response.headers.get( 'X-WP-TotalPages' ) ) || 1,
	};
}

export const updateDocument = ( id, data ) =>
	apiFetch( { path: `${ BASE }/documents/${ id }`, method: 'PATCH', data } );

export const trashDocument = ( id ) =>
	apiFetch( { path: `${ BASE }/documents/${ id }`, method: 'DELETE' } );

export const bulkDocuments = ( ids, action, value = '' ) =>
	apiFetch( {
		path: `${ BASE }/documents/bulk`,
		method: 'POST',
		data: { ids, action, value },
	} );

export const createCategory = ( name, parent = 0 ) =>
	apiFetch( {
		path: `${ BASE }/categories`,
		method: 'POST',
		data: { name, parent },
	} );

export const updateCategory = ( id, data ) =>
	apiFetch( { path: `${ BASE }/categories/${ id }`, method: 'PATCH', data } );

export const deleteCategory = ( id ) =>
	apiFetch( { path: `${ BASE }/categories/${ id }`, method: 'DELETE' } );

export const reorderCategories = ( ids ) =>
	apiFetch( {
		path: `${ BASE }/categories/reorder`,
		method: 'POST',
		data: { ids },
	} );

function sendFile( route, file, fields, onProgress ) {
	return new Promise( ( resolve, reject ) => {
		const xhr = new window.XMLHttpRequest();
		xhr.open( 'POST', config.restUrl + route );
		xhr.setRequestHeader( 'X-WP-Nonce', config.nonce );
		xhr.upload.onprogress = ( event ) => {
			if ( event.lengthComputable && onProgress ) {
				onProgress( event.loaded / event.total );
			}
		};
		xhr.onload = () => {
			let data = null;
			try {
				data = JSON.parse( xhr.responseText );
			} catch {
				// The server answered with something that is not JSON (a PHP error page).
			}
			if ( xhr.status >= 200 && xhr.status < 300 && data ) {
				resolve( data );
			} else {
				reject(
					new Error(
						data?.message ||
							__( 'The server rejected the file.', 'crnube-docs' )
					)
				);
			}
		};
		xhr.onerror = () =>
			reject(
				new Error(
					__( 'Network error. Check your connection.', 'crnube-docs' )
				)
			);

		const form = new window.FormData();
		form.append( 'file', file );
		Object.entries( fields ).forEach( ( [ key, value ] ) =>
			form.append( key, value )
		);
		xhr.send( form );
	} );
}

export const uploadDocument = ( file, { category, status }, onProgress ) =>
	sendFile(
		'documents/upload',
		file,
		{ category: category || 0, status },
		onProgress
	);

export const replaceDocumentFile = ( id, file, onProgress ) =>
	sendFile( `documents/${ id }/file`, file, {}, onProgress );

/** Puts a category inside `parent` at position `index` among its siblings. */
export const moveCategory = ( id, parent, index ) =>
	apiFetch( {
		path: `${ BASE }/categories/move`,
		method: 'POST',
		data: { id, parent, index },
	} );

/** Sets a category's ordering mode (`sort`) and/or its manual document order (`ids`). */
export const setCategoryOrder = ( id, data ) =>
	apiFetch( {
		path: `${ BASE }/categories/${ id }/order`,
		method: 'POST',
		data,
	} );

/** Makes a category public, private for `groups`, or inheriting (access ''). */
export const setCategoryAccess = ( id, access, groups ) =>
	apiFetch( {
		path: `${ BASE }/categories/${ id }/access`,
		method: 'POST',
		data: { access, groups },
	} );
