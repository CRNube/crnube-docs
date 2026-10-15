import { useCallback, useState } from '@wordpress/element';
import { Snackbar } from '@wordpress/components';
import { uid } from '../utils';

/** Short-lived messages ("3 documents moved") shown bottom left. */
export function useNotices() {
	const [ notices, setNotices ] = useState( [] );
	const remove = useCallback(
		( key ) =>
			setNotices( ( list ) => list.filter( ( n ) => n.key !== key ) ),
		[]
	);
	const push = useCallback(
		( message, type ) => {
			const key = uid();
			setNotices( ( list ) => [
				...list.slice( -3 ),
				{ key, message, type },
			] );
			setTimeout( () => remove( key ), type === 'error' ? 8000 : 4000 );
		},
		[ remove ]
	);
	return {
		notices,
		remove,
		success: useCallback(
			( message ) => push( message, 'success' ),
			[ push ]
		),
		error: useCallback( ( message ) => push( message, 'error' ), [ push ] ),
	};
}

export function NoticeList( { notices, onRemove } ) {
	return (
		<div className="crnd-notices" aria-live="polite">
			{ notices.map( ( notice ) => (
				<Snackbar
					key={ notice.key }
					className={ notice.type === 'error' ? 'is-error' : '' }
					onRemove={ () => onRemove( notice.key ) }
					explicitDismiss={ notice.type === 'error' }
				>
					{ notice.message }
				</Snackbar>
			) ) }
		</div>
	);
}
