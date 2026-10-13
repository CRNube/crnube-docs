import { __, sprintf } from '@wordpress/i18n';

export const config = window.crndLibrary || {};

/** Drag type used when document rows are dragged onto a category. */
export const DOC_DRAG_TYPE = 'application/x-crnd-documents';

/** Drag type used when a category is dragged inside the tree. */
export const CAT_DRAG_TYPE = 'application/x-crnd-category';

/** Flat categories (in display order) to a nested tree. */
export function buildTree( categories ) {
	const byParent = new Map();
	categories.forEach( ( category ) => {
		if ( ! byParent.has( category.parent ) ) {
			byParent.set( category.parent, [] );
		}
		byParent.get( category.parent ).push( category );
	} );
	const make = ( parent, depth ) =>
		( byParent.get( parent ) || [] ).map( ( category ) => {
			const children = make( category.id, depth + 1 );
			return {
				...category,
				depth,
				children,
				total:
					category.count +
					children.reduce( ( sum, child ) => sum + child.total, 0 ),
			};
		} );
	return make( 0, 0 );
}

export const flatten = ( nodes ) =>
	nodes.flatMap( ( node ) => [ node, ...flatten( node.children ) ] );

/** The tree in the shape TreeSelect expects. */
export const toTreeSelect = ( nodes ) =>
	nodes.map( ( node ) => ( {
		id: String( node.id ),
		name: node.name,
		children: toTreeSelect( node.children ),
	} ) );

/** "Estados financieros › Auditados" for a category id. */
export function categoryPath( id, byId ) {
	const names = [];
	let current = byId.get( id );
	while ( current ) {
		names.unshift( current.name );
		current = byId.get( current.parent );
	}
	return names.join( ' › ' );
}

export function formatSize( bytes ) {
	if ( ! bytes ) {
		return '';
	}
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let size = bytes;
	let unit = 0;
	while ( size >= 1024 && unit < units.length - 1 ) {
		size /= 1024;
		unit++;
	}
	return `${ size.toLocaleString( undefined, {
		maximumFractionDigits: unit > 1 ? 1 : 0,
	} ) } ${ units[ unit ] }`;
}

export const extensionOf = ( name ) =>
	( name.includes( '.' ) ? name.split( '.' ).pop() : '' ).toLowerCase();

/** Why a file cannot be uploaded, or '' if it can. */
export function validateFile( file ) {
	const ext = extensionOf( file.name );
	if ( ! ( config.extensions || [] ).includes( ext ) ) {
		return sprintf(
			/* translators: %s: file extension */
			__( '.%s files are not allowed.', 'crnube-docs' ),
			ext || '?'
		);
	}
	if ( config.maxUploadSize && file.size > config.maxUploadSize ) {
		return sprintf(
			/* translators: %s: maximum file size */
			__( 'The file is larger than the %s limit.', 'crnube-docs' ),
			formatSize( config.maxUploadSize )
		);
	}
	return '';
}

export const statusLabel = ( status ) =>
	( {
		publish: __( 'Published', 'crnube-docs' ),
		draft: __( 'Draft', 'crnube-docs' ),
		pending: __( 'Pending', 'crnube-docs' ),
		future: __( 'Scheduled', 'crnube-docs' ),
		private: __( 'Private', 'crnube-docs' ),
	} )[ status ] || status;

export const shortcodeFor = ( category ) =>
	`[${ config.shortcodeTag || 'crnube_docs' }${
		category ? ` category="${ category.slug }"` : ''
	}]`;

let counter = 0;
export const uid = () => `u${ Date.now() }-${ ++counter }`;
