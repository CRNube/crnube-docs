import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';
import './library.scss';

domReady( () => {
	const root = document.getElementById( 'crnd-library-root' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
} );
