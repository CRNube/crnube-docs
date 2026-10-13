import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDebounce } from '@wordpress/compose';
import {
	Button,
	FormFileUpload,
	SearchControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import * as api from './api';
import CategoryTree from './components/CategoryTree';
import DocumentList from './components/DocumentList';
import BulkBar from './components/BulkBar';
import UploadPanel from './components/UploadPanel';
import { CategoryModal, ConfirmModal } from './components/Modals';
import { NoticeList, useNotices } from './components/Notices';
import {
	DOC_DRAG_TYPE,
	buildTree,
	flatten,
	categoryPath,
	config,
	shortcodeFor,
	uid,
	validateFile,
} from './utils';

const PER_PAGE = 25;
const CATEGORY_PER_PAGE = 200;

/** Ordering modes of a category (CategoryOrder::MODES in PHP). */
const SORT_MODES = () => [
	{ value: '', label: __( 'As in the general settings', 'crnube-docs' ) },
	{ value: 'date_desc', label: __( 'Newest first', 'crnube-docs' ) },
	{ value: 'date_asc', label: __( 'Oldest first', 'crnube-docs' ) },
	{ value: 'title_asc', label: __( 'Title A–Z', 'crnube-docs' ) },
	{ value: 'title_desc', label: __( 'Title Z–A', 'crnube-docs' ) },
	{ value: 'manual', label: __( 'Manual (drag to arrange)', 'crnube-docs' ) },
];
const PARALLEL_UPLOADS = 3;

export default function App() {
	const notices = useNotices();
	const { error: notifyError, success: notifySuccess } = notices;

	const [ categoryData, setCategoryData ] = useState( {
		categories: [],
		total: 0,
		uncategorized: 0,
	} );
	const [ years, setYears ] = useState( [] );
	const [ selected, setSelected ] = useState( 0 );
	const [ search, setSearch ] = useState( '' );
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const [ year, setYear ] = useState( '' );
	const [ status, setStatus ] = useState( 'any' );
	const [ sort, setSort ] = useState( { orderby: 'date', order: 'desc' } );
	const [ page, setPage ] = useState( 1 );
	const [ list, setList ] = useState( { items: [], total: 0, pages: 1 } );
	const [ loading, setLoading ] = useState( true );
	const [ checked, setChecked ] = useState( () => new Set() );
	const [ uploads, setUploads ] = useState( [] );
	const [ publishOnUpload, setPublishOnUpload ] = useState(
		config.canPublish
	);
	const [ dragging, setDragging ] = useState( false );
	const [ modal, setModal ] = useState( null );

	const tree = useMemo(
		() => buildTree( categoryData.categories ),
		[ categoryData ]
	);
	const byId = useMemo(
		() => new Map( categoryData.categories.map( ( c ) => [ c.id, c ] ) ),
		[ categoryData ]
	);
	const current = selected > 0 ? byId.get( selected ) : null;
	const currentNode = useMemo(
		() => flatten( tree ).find( ( node ) => node.id === selected ) || null,
		[ tree, selected ]
	);

	const debouncedSearch = useDebounce( setSearchQuery, 300 );
	const request = useRef( 0 );

	const loadCategories = useCallback( async () => {
		try {
			const [ data, yearList ] = await Promise.all( [
				api.getCategories(),
				api.getYears(),
			] );
			setCategoryData( data );
			setYears( yearList );
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	}, [ notifyError ] );

	const loadDocuments = useCallback( async () => {
		const id = ++request.current;
		setLoading( true );
		try {
			const filters = { page, search: searchQuery, year, status };
			// Inside a category: its own documents (subcategories show as folders), in
			// the order the site shows them, all on one page so they can be dragged.
			const result = await api.getDocuments(
				selected > 0
					? {
							...filters,
							category: selected,
							direct: 1,
							orderby: 'site',
							per_page: CATEGORY_PER_PAGE,
					  }
					: {
							...filters,
							category: selected,
							per_page: PER_PAGE,
							...sort,
					  }
			);
			if ( id === request.current ) {
				setList( result );
			}
		} catch ( e ) {
			if ( id === request.current ) {
				notifyError( api.errorMessage( e ) );
			}
		} finally {
			if ( id === request.current ) {
				setLoading( false );
			}
		}
	}, [ page, selected, searchQuery, year, status, sort, notifyError ] );

	const refresh = useCallback(
		() => Promise.all( [ loadDocuments(), loadCategories() ] ),
		[ loadDocuments, loadCategories ]
	);

	useEffect( () => {
		loadCategories();
	}, [ loadCategories ] );

	useEffect( () => {
		loadDocuments();
	}, [ loadDocuments ] );

	// A new filter starts again on page one, with nothing selected.
	useEffect( () => {
		setPage( 1 );
		setChecked( new Set() );
	}, [ selected, searchQuery, year, status, sort ] );

	// If the selected category was deleted, go back to all documents.
	useEffect( () => {
		if (
			selected > 0 &&
			categoryData.categories.length &&
			! byId.has( selected )
		) {
			setSelected( 0 );
		}
	}, [ selected, byId, categoryData ] );

	/* ---------- Documents ---------- */

	const saveDocument = async ( id, changes ) => {
		// Show simple changes right away, so a quick second click on the status
		// toggles back instead of sending the same value twice.
		const instant = {};
		[ 'status', 'title' ].forEach( ( key ) => {
			if ( key in changes ) {
				instant[ key ] = changes[ key ];
			}
		} );
		setList( ( l ) => ( {
			...l,
			items: l.items.map( ( d ) =>
				d.id === id ? { ...d, ...instant } : d
			),
		} ) );
		try {
			const updated = await api.updateDocument( id, changes );
			setList( ( l ) => ( {
				...l,
				items: l.items.map( ( d ) => ( d.id === id ? updated : d ) ),
			} ) );
			if (
				'categories' in changes ||
				'status' in changes ||
				'year' in changes
			) {
				loadCategories();
			}
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
			loadDocuments(); // Undo the instant change.
		}
	};

	const trashDocuments = async ( ids ) => {
		try {
			const result = await api.bulkDocuments( ids, 'trash' );
			notifySuccess(
				sprintf(
					/* translators: %d: number of documents */
					_n(
						'%d document moved to the trash.',
						'%d documents moved to the trash.',
						result.done.length,
						'crnube-docs'
					),
					result.done.length
				)
			);
			setChecked( new Set() );
			refresh();
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	const applyBulk = async ( action, value, ids = [ ...checked ] ) => {
		if ( action === 'trash' ) {
			setModal( {
				type: 'confirm',
				title: __( 'Move to trash?', 'crnube-docs' ),
				message: sprintf(
					/* translators: %d: number of documents */
					_n(
						'%d document will stop showing on the site. It can be restored from the trash.',
						'%d documents will stop showing on the site. They can be restored from the trash.',
						ids.length,
						'crnube-docs'
					),
					ids.length
				),
				confirmLabel: __( 'Move to trash', 'crnube-docs' ),
				onConfirm: () => trashDocuments( ids ),
			} );
			return;
		}
		try {
			const result = await api.bulkDocuments( ids, action, value );
			const done = result.done.length;
			notifySuccess(
				action === 'move'
					? sprintf(
							/* translators: 1: number of documents, 2: category name */
							_n(
								'%1$d document moved to %2$s.',
								'%1$d documents moved to %2$s.',
								done,
								'crnube-docs'
							),
							done,
							byId.get( value )?.name || ''
					  )
					: sprintf(
							/* translators: %d: number of documents */
							_n(
								'%d document updated.',
								'%d documents updated.',
								done,
								'crnube-docs'
							),
							done
					  )
			);
			if ( result.failed.length ) {
				notifyError(
					sprintf(
						/* translators: %d: number of documents */
						_n(
							'%d document could not be changed.',
							'%d documents could not be changed.',
							result.failed.length,
							'crnube-docs'
						),
						result.failed.length
					)
				);
			}
			setChecked( new Set() );
			refresh();
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	/* ---------- Uploads ---------- */

	const updateUpload = ( key, changes ) =>
		setUploads( ( prev ) =>
			prev.map( ( u ) => ( u.key === key ? { ...u, ...changes } : u ) )
		);

	const uploadFiles = async (
		files,
		categoryId = selected > 0 ? selected : 0
	) => {
		if ( ! config.canUpload || files.length === 0 ) {
			return;
		}
		const entries = files.map( ( file ) => {
			const problem = validateFile( file );
			return {
				key: uid(),
				name: file.name,
				file,
				progress: 0,
				state: problem ? 'error' : 'queued',
				error: problem,
			};
		} );
		setUploads( ( prev ) => [
			...entries,
			...prev.filter(
				( u ) => u.state === 'queued' || u.state === 'uploading'
			),
		] );

		const queue = entries.filter( ( e ) => e.state === 'queued' );
		let next = 0;
		let done = 0;
		const worker = async () => {
			while ( next < queue.length ) {
				const entry = queue[ next++ ];
				updateUpload( entry.key, { state: 'uploading' } );
				try {
					const doc = await api.uploadDocument(
						entry.file,
						{
							category: categoryId,
							status: publishOnUpload ? 'publish' : 'draft',
						},
						( progress ) => updateUpload( entry.key, { progress } )
					);
					done++;
					updateUpload( entry.key, {
						state: 'done',
						progress: 1,
						doc,
					} );
				} catch ( e ) {
					updateUpload( entry.key, {
						state: 'error',
						error: api.errorMessage( e ),
					} );
				}
			}
		};
		await Promise.all( Array.from( { length: PARALLEL_UPLOADS }, worker ) );

		if ( done > 0 ) {
			const target = byId.get( categoryId );
			notifySuccess(
				target
					? sprintf(
							/* translators: 1: number of documents, 2: category name */
							_n(
								'%1$d document added to %2$s.',
								'%1$d documents added to %2$s.',
								done,
								'crnube-docs'
							),
							done,
							target.name
					  )
					: sprintf(
							/* translators: %d: number of documents */
							_n(
								'%d document added.',
								'%d documents added.',
								done,
								'crnube-docs'
							),
							done
					  )
			);
			refresh();
		}
	};

	const replaceFile = async ( doc, file ) => {
		const problem = validateFile( file );
		if ( problem ) {
			notifyError( problem );
			return;
		}
		try {
			const updated = await api.replaceDocumentFile( doc.id, file );
			setList( ( l ) => ( {
				...l,
				items: l.items.map( ( d ) =>
					d.id === doc.id ? updated : d
				),
			} ) );
			notifySuccess(
				__(
					'File replaced. The document links keep working.',
					'crnube-docs'
				)
			);
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	/* ---------- Categories ---------- */

	const saveCategory = async ( category, data ) => {
		try {
			const result = category
				? await api.updateCategory( category.id, data )
				: await api.createCategory( data.name, data.parent );
			setCategoryData( result );
			setModal( null );
			notifySuccess(
				category
					? __( 'Category saved.', 'crnube-docs' )
					: __( 'Category created.', 'crnube-docs' )
			);
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	const moveCategory = async ( node, siblings, direction ) => {
		const ids = siblings.map( ( s ) => s.id );
		const index = ids.indexOf( node.id );
		const target = index + direction;
		if ( target < 0 || target >= ids.length ) {
			return;
		}
		[ ids[ index ], ids[ target ] ] = [ ids[ target ], ids[ index ] ];
		try {
			setCategoryData( await api.reorderCategories( ids ) );
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	/** Drag and drop in the tree: reorder a category or put it inside another one. */
	const dropCategory = async ( id, parent, index ) => {
		try {
			setCategoryData( await api.moveCategory( id, parent, index ) );
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	/* ---------- Order of the documents inside a category ---------- */

	const setSortMode = async ( sortMode ) => {
		try {
			setCategoryData(
				await api.setCategoryOrder( selected, { sort: sortMode } )
			);
			loadDocuments();
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
		}
	};

	const reorderDocuments = async ( ids ) => {
		const wasManual = current?.sort === 'manual';
		const position = new Map( ids.map( ( id, i ) => [ id, i ] ) );
		setList( ( l ) => ( {
			...l,
			items: [ ...l.items ].sort(
				( a, b ) => position.get( a.id ) - position.get( b.id )
			),
		} ) );
		try {
			setCategoryData( await api.setCategoryOrder( selected, { ids } ) );
			if ( ! wasManual ) {
				notifySuccess(
					sprintf(
						/* translators: %s: category name */
						__(
							'"%s" now uses manual order: the site shows its documents in this order.',
							'crnube-docs'
						),
						current?.name || ''
					)
				);
			}
		} catch ( e ) {
			notifyError( api.errorMessage( e ) );
			loadDocuments();
		}
	};

	const deleteCategory = ( node ) =>
		setModal( {
			type: 'confirm',
			title: sprintf(
				/* translators: %s: category name */ __(
					'Delete "%s"?',
					'crnube-docs'
				),
				node.name
			),
			message:
				node.parent > 0
					? __(
							'Its documents move to the parent category and its subcategories move up one level. No document is deleted.',
							'crnube-docs'
					  )
					: __(
							'Its documents stay in the library without this category and its subcategories move up one level. No document is deleted.',
							'crnube-docs'
					  ),
			confirmLabel: __( 'Delete category', 'crnube-docs' ),
			onConfirm: async () => {
				try {
					setCategoryData( await api.deleteCategory( node.id ) );
					notifySuccess( __( 'Category deleted.', 'crnube-docs' ) );
					loadDocuments();
				} catch ( e ) {
					notifyError( api.errorMessage( e ) );
				}
			},
		} );

	const copyShortcode = async ( node ) => {
		try {
			await window.navigator.clipboard.writeText( shortcodeFor( node ) );
			notifySuccess(
				__(
					'Shortcode copied. Paste it in a page or an Elementor "Shortcode" widget.',
					'crnube-docs'
				)
			);
		} catch {
			notifyError( shortcodeFor( node ) );
		}
	};

	/* ---------- Dropping files on the page ---------- */

	const dragDepth = useRef( 0 );
	const isFileDrag = ( event ) =>
		[ ...event.dataTransfer.types ].includes( 'Files' ) &&
		! [ ...event.dataTransfer.types ].includes( DOC_DRAG_TYPE );
	const dropProps = config.canUpload
		? {
				onDragEnter: ( event ) => {
					if ( isFileDrag( event ) ) {
						dragDepth.current++;
						setDragging( true );
					}
				},
				onDragLeave: ( event ) => {
					if ( isFileDrag( event ) && --dragDepth.current <= 0 ) {
						dragDepth.current = 0;
						setDragging( false );
					}
				},
				onDragOver: ( event ) => {
					if ( isFileDrag( event ) ) {
						event.preventDefault();
					}
				},
				onDrop: ( event ) => {
					dragDepth.current = 0;
					setDragging( false );
					if ( isFileDrag( event ) ) {
						event.preventDefault();
						uploadFiles( [ ...event.dataTransfer.files ] );
					}
				},
		  }
		: {};

	/* ---------- Render ---------- */

	let heading = __( 'All documents', 'crnube-docs' );
	if ( selected === -1 ) {
		heading = __( 'Documents without category', 'crnube-docs' );
	} else if ( current ) {
		heading = categoryPath( current.id, byId );
	}

	const filtering = searchQuery !== '' || year !== '' || status !== 'any';

	const uploadButton = config.canUpload && (
		<FormFileUpload
			multiple
			accept={ config.accept }
			onChange={ ( event ) => uploadFiles( [ ...event.target.files ] ) }
			render={ ( { openFileDialog } ) => (
				<Button
					variant="primary"
					icon="upload"
					onClick={ openFileDialog }
					__next40pxDefaultSize
				>
					{ __( 'Upload documents', 'crnube-docs' ) }
				</Button>
			) }
		/>
	);

	const empty = (
		<div className="crnd-empty-state">
			<span
				className="dashicons dashicons-media-document"
				aria-hidden="true"
			/>
			{ filtering ? (
				<>
					<h3>
						{ __(
							'No documents match these filters',
							'crnube-docs'
						) }
					</h3>
					<Button
						variant="secondary"
						onClick={ () => {
							setSearch( '' );
							setSearchQuery( '' );
							setYear( '' );
							setStatus( 'any' );
						} }
					>
						{ __( 'Clear filters', 'crnube-docs' ) }
					</Button>
				</>
			) : (
				<>
					<h3>
						{ current
							? sprintf(
									/* translators: %s: category name */ __(
										'"%s" has no documents yet',
										'crnube-docs'
									),
									current.name
							  )
							: __( 'Your library is empty', 'crnube-docs' ) }
					</h3>
					{ config.canUpload && (
						<p>
							{ __(
								'Drag your PDF, Word or Excel files here, or use the button.',
								'crnube-docs'
							) }
						</p>
					) }
					{ uploadButton }
				</>
			) }
		</div>
	);

	return (
		<div className="crnd-lib">
			<CategoryTree
				tree={ tree }
				selected={ selected }
				onSelect={ setSelected }
				total={ categoryData.total }
				uncategorized={ categoryData.uncategorized }
				canManage={ config.canManageCategories }
				onAdd={ ( parent ) => setModal( { type: 'category', parent } ) }
				onRename={ ( category ) =>
					setModal( { type: 'category', category } )
				}
				onMove={ moveCategory }
				onDelete={ deleteCategory }
				onCopyShortcode={ copyShortcode }
				onDropDocuments={ ( categoryId, ids ) =>
					applyBulk( 'move', categoryId, ids )
				}
				onDropFiles={ ( categoryId, files ) =>
					uploadFiles( files, categoryId )
				}
				onMoveCategory={ dropCategory }
			/>

			<main className="crnd-lib__main" { ...dropProps }>
				<header className="crnd-lib__header">
					<div className="crnd-lib__heading">
						<h1>{ heading }</h1>
						<span className="crnd-lib__total">
							{ sprintf(
								/* translators: %d: number of documents */
								_n(
									'%d document',
									'%d documents',
									list.total,
									'crnube-docs'
								),
								list.total
							) }
						</span>
					</div>
					<div className="crnd-lib__header-actions">
						{ current && (
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								className="crnd-lib__sortmode"
								label={ __(
									'Order on the site',
									'crnube-docs'
								) }
								value={ current.sort || '' }
								options={ SORT_MODES() }
								onChange={ setSortMode }
							/>
						) }
						{ current && (
							<Button
								variant="tertiary"
								icon="shortcode"
								onClick={ () => copyShortcode( current ) }
							>
								{ __( 'Copy shortcode', 'crnube-docs' ) }
							</Button>
						) }
						{ uploadButton }
					</div>
				</header>

				<div className="crnd-lib__toolbar">
					<SearchControl
						__nextHasNoMarginBottom
						className="crnd-lib__search"
						label={ __( 'Search documents', 'crnube-docs' ) }
						placeholder={ __( 'Search by title…', 'crnube-docs' ) }
						value={ search }
						onChange={ ( value ) => {
							setSearch( value );
							debouncedSearch( value );
						} }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Year', 'crnube-docs' ) }
						hideLabelFromVision
						value={ year }
						options={ [
							{
								value: '',
								label: __( 'All years', 'crnube-docs' ),
							},
							...years.map( ( y ) => ( { value: y, label: y } ) ),
						] }
						onChange={ setYear }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Status', 'crnube-docs' ) }
						hideLabelFromVision
						value={ status }
						options={ [
							{
								value: 'any',
								label: __( 'Any status', 'crnube-docs' ),
							},
							{
								value: 'publish',
								label: __( 'Published', 'crnube-docs' ),
							},
							{
								value: 'draft',
								label: __( 'Drafts', 'crnube-docs' ),
							},
							{
								value: 'pending',
								label: __( 'Pending', 'crnube-docs' ),
							},
						] }
						onChange={ setStatus }
					/>
					{ config.canUpload && config.canPublish && (
						<ToggleControl
							__nextHasNoMarginBottom
							className="crnd-lib__publish-toggle"
							label={ __( 'Publish on upload', 'crnube-docs' ) }
							checked={ publishOnUpload }
							onChange={ setPublishOnUpload }
						/>
					) }
				</div>

				{ checked.size > 0 && (
					<BulkBar
						count={ checked.size }
						tree={ tree }
						onApply={ applyBulk }
						onClear={ () => setChecked( new Set() ) }
					/>
				) }

				<DocumentList
					items={ list.items }
					loading={ loading }
					checked={ checked }
					onToggle={ ( id ) =>
						setChecked( ( set ) => {
							const next = new Set( set );
							if ( next.has( id ) ) {
								next.delete( id );
							} else {
								next.add( id );
							}
							return next;
						} )
					}
					onToggleAll={ ( on ) =>
						setChecked(
							on
								? new Set( list.items.map( ( d ) => d.id ) )
								: new Set()
						)
					}
					tree={ tree }
					byId={ byId }
					sort={ sort }
					onSort={ setSort }
					sortable={ selected <= 0 }
					folders={
						currentNode && ! filtering ? currentNode.children : []
					}
					reorderable={
						selected > 0 && ! filtering && list.pages <= 1
					}
					onReorder={ reorderDocuments }
					onOpenCategory={ setSelected }
					onDropDocuments={ ( categoryId, ids ) =>
						applyBulk( 'move', categoryId, ids )
					}
					onDropFiles={ ( categoryId, files ) =>
						uploadFiles( files, categoryId )
					}
					onSave={ saveDocument }
					onTrash={ ( doc ) => applyBulk( 'trash', '', [ doc.id ] ) }
					onReplace={ replaceFile }
					empty={ empty }
				/>

				{ list.pages > 1 && (
					<nav
						className="crnd-pager"
						aria-label={ __( 'Pages', 'crnube-docs' ) }
					>
						<Button
							variant="secondary"
							icon="arrow-left-alt2"
							disabled={ page <= 1 }
							onClick={ () => setPage( page - 1 ) }
							label={ __( 'Previous page', 'crnube-docs' ) }
						/>
						<span>
							{ sprintf(
								/* translators: 1: current page, 2: total pages */ __(
									'Page %1$d of %2$d',
									'crnube-docs'
								),
								page,
								list.pages
							) }
						</span>
						<Button
							variant="secondary"
							icon="arrow-right-alt2"
							disabled={ page >= list.pages }
							onClick={ () => setPage( page + 1 ) }
							label={ __( 'Next page', 'crnube-docs' ) }
						/>
					</nav>
				) }

				{ dragging && (
					<div className="crnd-dropzone" aria-hidden="true">
						<div className="crnd-dropzone__inner">
							<span className="dashicons dashicons-upload" />
							<strong>
								{ __(
									'Drop the files to upload them',
									'crnube-docs'
								) }
							</strong>
							<span>
								{ current
									? sprintf(
											/* translators: %s: category name */ __(
												'They will be added to "%s".',
												'crnube-docs'
											),
											current.name
									  )
									: __(
											'They will be added without a category. Drop them on a category on the left to file them there.',
											'crnube-docs'
									  ) }
							</span>
						</div>
					</div>
				) }
			</main>

			<UploadPanel
				uploads={ uploads }
				onDismiss={ () => setUploads( [] ) }
			/>
			<NoticeList
				notices={ notices.notices }
				onRemove={ notices.remove }
			/>

			{ modal?.type === 'category' && (
				<CategoryModal
					category={ modal.category }
					parent={ modal.parent }
					tree={ tree }
					onSave={ ( data ) => saveCategory( modal.category, data ) }
					onClose={ () => setModal( null ) }
				/>
			) }
			{ modal?.type === 'confirm' && (
				<ConfirmModal
					title={ modal.title }
					message={ modal.message }
					confirmLabel={ modal.confirmLabel }
					onConfirm={ modal.onConfirm }
					onClose={ () => setModal( null ) }
				/>
			) }
		</div>
	);
}
