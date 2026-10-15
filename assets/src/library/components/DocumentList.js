import { useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import Checkbox from './Checkbox';
import DocumentRow from './DocumentRow';
import { DOC_DRAG_TYPE } from '../utils';
import { documentColumns } from '../extensions';

const SortHeader = ( { field, label, sort, onSort, sortable } ) => {
	if ( ! sortable ) {
		return label;
	}
	const active = sort.orderby === field;
	// Titles start A–Z, dates newest first; clicking again flips the direction.
	let order = field === 'title' ? 'asc' : 'desc';
	if ( active ) {
		order = sort.order === 'desc' ? 'asc' : 'desc';
	}
	return (
		<button
			type="button"
			className={ `crnd-sort ${ active ? 'is-active' : '' }` }
			onClick={ () => onSort( { orderby: field, order } ) }
		>
			{ label }
			{ active && (
				<span
					className={ `dashicons dashicons-arrow-${
						sort.order === 'asc' ? 'up' : 'down'
					}-alt2` }
					aria-hidden="true"
				/>
			) }
		</button>
	);
};

/**
 * Subcategories of the open category, shown as folders above its documents. They open
 * on click and accept dropped documents and files, like the tree.
 */
function FolderRow( {
	folder,
	columns,
	onOpen,
	onDropDocuments,
	onDropFiles,
} ) {
	const [ over, setOver ] = useState( false );
	return (
		<tr
			className={ `crnd-folder-row ${ over ? 'is-drop-target' : '' }` }
			onDragOver={ ( event ) => {
				const types = [ ...event.dataTransfer.types ];
				if (
					types.includes( DOC_DRAG_TYPE ) ||
					types.includes( 'Files' )
				) {
					event.preventDefault();
					event.stopPropagation();
					setOver( true );
				}
			} }
			onDragLeave={ () => setOver( false ) }
			onDrop={ ( event ) => {
				setOver( false );
				const docs = event.dataTransfer.getData( DOC_DRAG_TYPE );
				if ( docs ) {
					event.preventDefault();
					event.stopPropagation();
					onDropDocuments( folder.id, JSON.parse( docs ) );
				} else if ( event.dataTransfer.files.length ) {
					event.preventDefault();
					event.stopPropagation();
					onDropFiles( folder.id, [ ...event.dataTransfer.files ] );
				}
			} }
		>
			<td colSpan={ columns }>
				<button
					type="button"
					className="crnd-folder"
					onClick={ () => onOpen( folder.id ) }
				>
					<span
						className="dashicons dashicons-category"
						aria-hidden="true"
					/>
					<span className="crnd-folder__name">{ folder.name }</span>
					<span className="crnd-folder__count">
						{ sprintf(
							/* translators: %d: number of documents */
							_n(
								'%d document',
								'%d documents',
								folder.total,
								'crnube-docs'
							),
							folder.total
						) }
					</span>
					<span
						className="dashicons dashicons-arrow-right-alt2"
						aria-hidden="true"
					/>
				</button>
			</td>
		</tr>
	);
}

export default function DocumentList( {
	items,
	folders = [],
	loading,
	checked,
	onToggle,
	onToggleAll,
	tree,
	byId,
	sort,
	onSort,
	sortable,
	reorderable,
	onReorder,
	onOpenCategory,
	onDropDocuments,
	onDropFiles,
	onSave,
	onTrash,
	onReplace,
	empty,
} ) {
	const fileInput = useRef();
	const replacing = useRef( null );
	const dragged = useRef( null );
	const [ indicator, setIndicator ] = useState( null );

	const allChecked =
		items.length > 0 && items.every( ( item ) => checked.has( item.id ) );
	const extraColumns = useMemo( () => documentColumns(), [] );
	const columns = ( reorderable ? 9 : 8 ) + extraColumns.length;

	const indicatorFor = ( id ) => {
		if ( indicator?.id !== id ) {
			return null;
		}
		return indicator.before ? 'before' : 'after';
	};

	const pickReplacement = ( doc ) => {
		replacing.current = doc;
		fileInput.current.value = '';
		fileInput.current.click();
	};

	/** Moves the given documents next to `targetId` and saves the new order. */
	const placeBeside = ( ids, targetId, before ) => {
		const order = items.map( ( d ) => d.id );
		const rest = order.filter( ( id ) => ! ids.includes( id ) );
		let index = rest.indexOf( targetId );
		if ( index === -1 ) {
			return; // Dropped onto one of the rows being moved.
		}
		const moving = order.filter( ( id ) => ids.includes( id ) );
		if ( ! before ) {
			index++;
		}
		rest.splice( index, 0, ...moving );
		if ( rest.join() !== order.join() ) {
			onReorder( rest );
		}
	};

	const moveTo = ( doc, where ) => {
		const order = items.map( ( d ) => d.id );
		const index = order.indexOf( doc.id );
		order.splice( index, 1 );
		const target = {
			top: 0,
			up: Math.max( 0, index - 1 ),
			down: Math.min( order.length, index + 1 ),
			bottom: order.length,
		}[ where ];
		order.splice( target, 0, doc.id );
		onReorder( order );
	};

	const rowDragProps = ( doc ) => ( {
		draggable: true,
		onDragStart: ( event ) => {
			const ids = checked.has( doc.id ) ? [ ...checked ] : [ doc.id ];
			dragged.current = ids;
			event.dataTransfer.setData( DOC_DRAG_TYPE, JSON.stringify( ids ) );
			event.dataTransfer.effectAllowed = 'move';
		},
		onDragEnd: () => {
			dragged.current = null;
			setIndicator( null );
		},
		onDragOver: ( event ) => {
			if ( ! reorderable || ! dragged.current ) {
				return;
			}
			event.preventDefault();
			const rect = event.currentTarget.getBoundingClientRect();
			const before = event.clientY < rect.top + rect.height / 2;
			setIndicator( ( current ) =>
				current?.id === doc.id && current.before === before
					? current
					: { id: doc.id, before }
			);
		},
		onDrop: ( event ) => {
			if ( ! reorderable || ! dragged.current ) {
				return;
			}
			event.preventDefault();
			const rect = event.currentTarget.getBoundingClientRect();
			placeBeside(
				dragged.current,
				doc.id,
				event.clientY < rect.top + rect.height / 2
			);
			dragged.current = null;
			setIndicator( null );
		},
	} );

	if ( ! loading && items.length === 0 && folders.length === 0 ) {
		return empty;
	}

	return (
		<div className={ `crnd-table-wrap ${ loading ? 'is-loading' : '' }` }>
			{ loading && (
				<div className="crnd-table-loading">
					<Spinner />
				</div>
			) }
			<input
				ref={ fileInput }
				type="file"
				hidden
				accept={ window.crndLibrary?.accept }
				onChange={ ( event ) => {
					const file = event.target.files[ 0 ];
					if ( file && replacing.current ) {
						onReplace( replacing.current, file );
					}
				} }
			/>
			<table className="crnd-table">
				<thead>
					<tr>
						{ reorderable && (
							<th className="crnd-col-handle">
								<span className="screen-reader-text">
									{ __( 'Order', 'crnube-docs' ) }
								</span>
							</th>
						) }
						<th className="crnd-col-check">
							<Checkbox
								label={ __( 'Select all', 'crnube-docs' ) }
								checked={ allChecked }
								indeterminate={
									! allChecked &&
									items.some( ( item ) =>
										checked.has( item.id )
									)
								}
								onChange={ onToggleAll }
							/>
						</th>
						<th className="crnd-col-title">
							<SortHeader
								field="title"
								label={ __( 'Document', 'crnube-docs' ) }
								sort={ sort }
								onSort={ onSort }
								sortable={ sortable }
							/>
						</th>
						<th className="crnd-col-cats">
							{ __( 'Categories', 'crnube-docs' ) }
						</th>
						{ extraColumns.map( ( column ) => (
							<th
								key={ column.id }
								className={ column.className }
							>
								{ column.label }
							</th>
						) ) }
						<th className="crnd-col-year">
							{ __( 'Year', 'crnube-docs' ) }
						</th>
						<th className="crnd-col-status">
							{ __( 'Status', 'crnube-docs' ) }
						</th>
						<th className="crnd-col-date">
							<SortHeader
								field="date"
								label={ __( 'Published', 'crnube-docs' ) }
								sort={ sort }
								onSort={ onSort }
								sortable={ sortable }
							/>
						</th>
						<th
							className="crnd-col-downloads"
							title={ __( 'Downloads and views', 'crnube-docs' ) }
						>
							<span
								className="dashicons dashicons-chart-bar"
								aria-hidden="true"
							/>
							<span className="screen-reader-text">
								{ __( 'Downloads and views', 'crnube-docs' ) }
							</span>
						</th>
						<th className="crnd-col-actions">
							<span className="screen-reader-text">
								{ __( 'Actions', 'crnube-docs' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ folders.map( ( folder ) => (
						<FolderRow
							key={ `folder-${ folder.id }` }
							folder={ folder }
							columns={ columns }
							onOpen={ onOpenCategory }
							onDropDocuments={ onDropDocuments }
							onDropFiles={ onDropFiles }
						/>
					) ) }
					{ items.map( ( doc, index ) => (
						<DocumentRow
							key={ doc.id }
							doc={ doc }
							index={ index }
							count={ items.length }
							checked={ checked.has( doc.id ) }
							onToggle={ () => onToggle( doc.id ) }
							tree={ tree }
							extraColumns={ extraColumns }
							byId={ byId }
							reorderable={ reorderable }
							dropIndicator={ indicatorFor( doc.id ) }
							dragProps={ rowDragProps( doc ) }
							onMove={ ( where ) => moveTo( doc, where ) }
							onSave={ onSave }
							onTrash={ onTrash }
							onReplace={ () => pickReplacement( doc ) }
						/>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
