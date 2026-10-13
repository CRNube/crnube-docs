/* global wp, crndMigrate */
/**
 * Documents > Import: the three steps of the FIVO Docs migration.
 * All text goes in with textContent, never as HTML.
 */
( function () {
	'use strict';

	const root = document.getElementById( 'crnd-migrate-root' );
	if ( ! root || ! wp.apiFetch ) {
		return;
	}
	const { __, _n, sprintf } = wp.i18n;
	const api = ( path, data ) =>
		wp.apiFetch( { path: '/crnd/v1/migrate/fivo' + path, method: data ? 'POST' : 'GET', data } );

	const state = { taxonomy: '', skipGeneric: true, cleanTitles: true, status: null, busy: false };

	/** Tiny element builder: h( 'p', { className: 'x' }, 'text', child ). */
	function h( tag, props, ...children ) {
		const el = document.createElement( tag );
		Object.entries( props || {} ).forEach( ( [ key, value ] ) => {
			if ( key.startsWith( 'on' ) ) {
				el.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( key === 'className' ) {
				el.className = value;
			} else if ( value !== false && value !== null && value !== undefined ) {
				el.setAttribute( key, value === true ? '' : value );
			}
		} );
		children.flat().forEach( ( child ) => {
			if ( child === null || child === false || child === undefined ) {
				return;
			}
			el.append( child instanceof Node ? child : document.createTextNode( String( child ) ) );
		} );
		return el;
	}

	const notice = ( type, text ) => h( 'div', { className: `notice notice-${ type } inline` }, h( 'p', {}, text ) );

	async function load() {
		const query = new URLSearchParams( { taxonomy: state.taxonomy, skip_generic: state.skipGeneric ? 1 : 0 } );
		state.status = await api( '?' + query.toString() );
		state.taxonomy = state.status.taxonomy;
		render();
	}

	function stepSource( s ) {
		const step = h( 'section', { className: 'crnd-card crnd-migrate__step' }, h( 'h2', {}, __( '1. What will be imported', 'crnube-docs' ) ) );

		if ( s.sources.length > 1 ) {
			const select = h(
				'select',
				{ onChange: ( e ) => { state.taxonomy = e.target.value; load(); } },
				s.sources.map( ( src ) =>
					h( 'option', { value: src.name, selected: src.name === state.taxonomy },
						`${ src.label } (${ src.name }) — ${ src.attachments } ${ __( 'files', 'crnube-docs' ) }${ src.fivo ? ' · FIVO' : '' }` )
				)
			);
			step.append( h( 'p', {}, h( 'label', {}, __( 'Media categories to import:', 'crnube-docs' ), ' ', select ) ) );
		} else {
			const src = s.sources[ 0 ];
			step.append( h( 'p', {}, sprintf(
				/* translators: 1: taxonomy label, 2: number of categories, 3: number of files */
				__( 'Found "%1$s": %2$d categories and %3$d files.', 'crnube-docs' ),
				src.label, src.terms, src.attachments
			) ) );
		}

		const option = ( key, label, help ) =>
			h( 'label', { className: 'crnd-migrate__option' },
				h( 'input', { type: 'checkbox', checked: state[ key ], onChange: ( e ) => { state[ key ] = e.target.checked; if ( key === 'skipGeneric' ) { load(); } } } ),
				' ', h( 'strong', {}, label ), h( 'span', {}, help )
			);
		step.append(
			option( 'skipGeneric', __( 'Skip generic subcategories', 'crnube-docs' ), __( 'Levels like "Ver Aquí" or "Documentos" that FIVO needed only for its layout: their files go to the parent category.', 'crnube-docs' ) ),
			option( 'cleanTitles', __( 'Clean up titles', 'crnube-docs' ), __( '"Informe-Anual_2024" becomes "Informe Anual 2024".', 'crnube-docs' ) )
		);

		const list = h( 'ul', { className: 'crnd-migrate__tree' } );
		s.preview.forEach( ( row ) => {
			list.append( h( 'li', { className: row.skipped ? 'is-skipped' : '', style: `padding-left:${ row.depth * 20 }px` },
				h( 'span', { className: `dashicons dashicons-${ row.skipped ? 'migrate' : 'category' }`, 'aria-hidden': 'true' } ),
				' ', row.name, ' ',
				h( 'span', { className: 'crnd-migrate__count' }, String( row.attachments ) ),
				row.skipped ? h( 'em', {}, ' — ' + __( 'joined to the parent category', 'crnube-docs' ) ) : null,
				row.imported ? h( 'span', { className: 'crnd-migrate__tag' }, __( 'already imported', 'crnube-docs' ) ) : null
			) );
		} );
		step.append( list );
		return step;
	}

	function stepImport( s ) {
		const total = s.sources.find( ( src ) => src.name === state.taxonomy )?.attachments || 0;
		const progress = h( 'progress', { max: Math.max( total, 1 ), value: 0, hidden: true } );
		const result = h( 'div', { 'aria-live': 'polite' } );
		const button = h( 'button', {
			type: 'button',
			className: 'button button-primary button-hero',
			onClick: async () => {
				button.disabled = true;
				progress.hidden = false;
				result.replaceChildren( h( 'p', {}, __( 'Creating categories…', 'crnube-docs' ) ) );
				try {
					await api( '/categories', { taxonomy: state.taxonomy, skip_generic: state.skipGeneric } );
					const sum = { created: 0, updated: 0, skipped: 0 };
					let offset = 0;
					while ( offset >= 0 ) {
						const r = await api( '/documents', { taxonomy: state.taxonomy, offset, clean_titles: state.cleanTitles } );
						sum.created += r.created;
						sum.updated += r.updated;
						sum.skipped += r.skipped;
						progress.value = r.done;
						result.replaceChildren( h( 'p', {}, sprintf( __( 'Documents: %1$d of %2$d…', 'crnube-docs' ), r.done, r.total ) ) );
						offset = r.next;
					}
					result.replaceChildren(
						notice( 'success', [
							sprintf( _n( '%d document created.', '%d documents created.', sum.created, 'crnube-docs' ), sum.created ),
							sum.updated ? sprintf( _n( '%d already existed (categories updated).', '%d already existed (categories updated).', sum.updated, 'crnube-docs' ), sum.updated ) : '',
							sum.skipped ? sprintf( _n( '%d file skipped (type not allowed).', '%d files skipped (type not allowed).', sum.skipped, 'crnube-docs' ), sum.skipped ) : '',
						].filter( Boolean ).join( ' ' ) ),
						h( 'p', {}, h( 'a', { href: crndMigrate.libraryUrl, className: 'button' }, __( 'Review them in the Library', 'crnube-docs' ) ) )
					);
					await load();
				} catch ( e ) {
					result.replaceChildren( notice( 'error', e.message || __( 'The import stopped. You can run it again: it continues without duplicating.', 'crnube-docs' ) ) );
					button.disabled = false;
				}
			},
		}, __( 'Import categories and documents', 'crnube-docs' ) );

		return h( 'section', { className: 'crnd-card crnd-migrate__step' },
			h( 'h2', {}, __( '2. Import', 'crnube-docs' ) ),
			h( 'p', {}, __( 'Creates the categories and one document per file. Nothing changes on the site yet.', 'crnube-docs' ) ),
			button, progress, result
		);
	}

	function stepPages( s ) {
		const step = h( 'section', { className: 'crnd-card crnd-migrate__step' },
			h( 'h2', {}, __( '3. Replace the FIVO shortcodes on the pages', 'crnube-docs' ) ),
			h( 'p', {}, __( 'Each page is changed only when you click "Replace", with a backup you can restore with "Undo". Check the page right after.', 'crnube-docs' ) )
		);
		if ( s.pages.length === 0 ) {
			step.append( h( 'p', { className: 'crnd-muted' }, __( 'No page uses FIVO Docs shortcodes.', 'crnube-docs' ) ) );
			return step;
		}
		const table = h( 'table', { className: 'widefat striped crnd-migrate__pages' },
			h( 'thead', {}, h( 'tr', {},
				h( 'th', {}, __( 'Page', 'crnube-docs' ) ),
				h( 'th', {}, __( 'Shortcodes', 'crnube-docs' ) ),
				h( 'th', {}, '' )
			) )
		);
		const body = h( 'tbody' );
		s.pages.forEach( ( page ) => {
			const action = async ( path ) => {
				try {
					const r = await api( path, { taxonomy: state.taxonomy, post_id: page.id } );
					state.status.pages = r.pages;
					render();
				} catch ( e ) {
					window.alert( e.message );
				}
			};
			body.append( h( 'tr', {},
				h( 'td', {}, h( 'strong', {}, page.title ), h( 'br' ),
					h( 'a', { href: page.view, target: '_blank', rel: 'noopener' }, __( 'View', 'crnube-docs' ) ), ' · ',
					h( 'a', { href: page.edit }, __( 'Edit', 'crnube-docs' ) ) ),
				h( 'td', {}, page.items.length
					? page.items.map( ( item ) => h( 'div', { className: 'crnd-migrate__swap' },
						h( 'code', {}, item.original ), h( 'span', { 'aria-hidden': 'true' }, ' → ' ), h( 'code', {}, item.replacement ) ) )
					: h( 'em', {}, __( 'Replaced. No FIVO shortcodes left.', 'crnube-docs' ) ) ),
				h( 'td', {},
					page.items.length ? h( 'button', { type: 'button', className: 'button button-primary', onClick: () => action( '/replace' ) }, __( 'Replace', 'crnube-docs' ) ) : null,
					page.replaced ? h( 'button', { type: 'button', className: 'button', onClick: () => action( '/undo' ) }, __( 'Undo', 'crnube-docs' ) ) : null
				)
			) );
		} );
		table.append( body );
		step.append( table );
		return step;
	}

	function render() {
		const s = state.status;
		if ( ! s.sources.length ) {
			root.replaceChildren( notice( 'info', __( 'No FIVO Docs categories (or any media categories) were found on this site. Nothing to import.', 'crnube-docs' ) ) );
			return;
		}
		root.replaceChildren(
			s.fivoActive ? null : notice( 'warning', __( 'FIVO Docs is not active. Its categories were found anyway and can be imported.', 'crnube-docs' ) ),
			stepSource( s ),
			stepImport( s ),
			stepPages( s ),
			h( 'p', { className: 'crnd-muted' }, __( 'When every page is replaced and checked, FIVO Docs can be deactivated. The files stay in the media library.', 'crnube-docs' ) )
		);
	}

	load().catch( ( e ) => root.replaceChildren( notice( 'error', e.message || 'Error' ) ) );
} )();
