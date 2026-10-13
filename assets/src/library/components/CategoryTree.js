import { useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { CAT_DRAG_TYPE, DOC_DRAG_TYPE, flatten } from '../utils';

/**
 * Where a dragged category lands on a row: the top quarter puts it before the row,
 * the bottom quarter after it, and the middle inside it (as a subcategory).
 *
 * @param {DragEvent}   event
 * @param {HTMLElement} row
 * @return {'before'|'after'|'inside'} Drop zone.
 */
function zoneOf( event, row ) {
	const rect = row.getBoundingClientRect();
	const y = ( event.clientY - rect.top ) / rect.height;
	if ( y < 0.25 ) {
		return 'before';
	}
	return y > 0.75 ? 'after' : 'inside';
}

/**
 * Category tree on the left. A category accepts dropped document rows (moves them),
 * dropped files (uploads them into it) and other categories (reorders or nests them).
 */
export default function CategoryTree( {
	tree,
	selected,
	onSelect,
	total,
	uncategorized,
	canManage,
	onAdd,
	onRename,
	onMove,
	onDelete,
	onCopyShortcode,
	extraActions = [],
	onExtraAction,
	onDropDocuments,
	onDropFiles,
	onMoveCategory,
} ) {
	const [ collapsed, setCollapsed ] = useState( () => new Set() );
	const [ dropTarget, setDropTarget ] = useState( null );
	const [ catDrop, setCatDrop ] = useState( null );
	// dataTransfer cannot be read during dragover, so the dragged category is kept here.
	const draggedCategory = useRef( null );

	const nodes = useMemo( () => {
		const map = new Map();
		flatten( tree ).forEach( ( node ) => map.set( node.id, node ) );
		return map;
	}, [ tree ] );

	const isInside = ( id, ancestorId ) => {
		let node = nodes.get( id );
		while ( node ) {
			if ( node.id === ancestorId ) {
				return true;
			}
			node = nodes.get( node.parent );
		}
		return false;
	};

	const dropCategory = ( target, zone ) => {
		const dragged = draggedCategory.current;
		if ( ! dragged || isInside( target.id, dragged ) ) {
			return; // Onto itself or one of its own subcategories.
		}
		if ( zone === 'inside' ) {
			const children = target.children.filter(
				( c ) => c.id !== dragged
			);
			onMoveCategory( dragged, target.id, children.length );
			setCollapsed( ( current ) => {
				const next = new Set( current );
				next.delete( target.id );
				return next;
			} );
			return;
		}
		const siblings = (
			target.parent ? nodes.get( target.parent ).children : tree
		)
			.map( ( c ) => c.id )
			.filter( ( id ) => id !== dragged );
		const index =
			siblings.indexOf( target.id ) + ( zone === 'after' ? 1 : 0 );
		onMoveCategory( dragged, target.parent, index );
	};

	const categoryDragHandlers = ( node ) =>
		canManage
			? {
					draggable: true,
					onDragStart: ( event ) => {
						draggedCategory.current = node.id;
						event.dataTransfer.setData(
							CAT_DRAG_TYPE,
							String( node.id )
						);
						event.dataTransfer.effectAllowed = 'move';
						event.stopPropagation();
					},
					onDragEnd: () => {
						draggedCategory.current = null;
						setCatDrop( null );
					},
			  }
			: {};

	const toggle = ( id ) =>
		setCollapsed( ( current ) => {
			const next = new Set( current );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );

	const dropHandlers = ( id, node = null ) => ( {
		onDragOver: ( event ) => {
			const types = [ ...event.dataTransfer.types ];
			if ( node && types.includes( CAT_DRAG_TYPE ) ) {
				if ( isInside( node.id, draggedCategory.current ) ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				const zone = zoneOf( event, event.currentTarget );
				setCatDrop( ( current ) =>
					current?.id === node.id && current.zone === zone
						? current
						: { id: node.id, zone }
				);
				return;
			}
			if (
				types.includes( DOC_DRAG_TYPE ) ||
				( id > 0 && types.includes( 'Files' ) )
			) {
				event.preventDefault();
				event.stopPropagation();
				setDropTarget( id );
			}
		},
		onDragLeave: ( event ) => {
			if ( event.currentTarget.contains( event.relatedTarget ) ) {
				return;
			}
			setDropTarget( ( current ) => ( current === id ? null : current ) );
			setCatDrop( ( current ) =>
				current?.id === id ? null : current
			);
		},
		onDrop: ( event ) => {
			setDropTarget( null );
			if ( node && event.dataTransfer.getData( CAT_DRAG_TYPE ) ) {
				event.preventDefault();
				event.stopPropagation();
				setCatDrop( null );
				dropCategory( node, zoneOf( event, event.currentTarget ) );
				draggedCategory.current = null;
				return;
			}
			const docs = event.dataTransfer.getData( DOC_DRAG_TYPE );
			if ( docs ) {
				event.preventDefault();
				event.stopPropagation();
				if ( id > 0 ) {
					onDropDocuments( id, JSON.parse( docs ) );
				}
				return;
			}
			if ( id > 0 && event.dataTransfer.files.length ) {
				event.preventDefault();
				event.stopPropagation();
				onDropFiles( id, [ ...event.dataTransfer.files ] );
			}
		},
	} );

	const renderNode = ( node, index, siblings ) => {
		const isOpen = ! collapsed.has( node.id );
		const hasChildren = node.children.length > 0;
		return (
			<li key={ node.id } className="crnd-tree__item">
				<div
					className={ [
						'crnd-tree__row',
						selected === node.id && 'is-selected',
						dropTarget === node.id && 'is-drop-target',
						catDrop?.id === node.id && `is-drop-${ catDrop.zone }`,
					]
						.filter( Boolean )
						.join( ' ' ) }
					style={ { '--depth': node.depth } }
					{ ...dropHandlers( node.id, node ) }
					{ ...categoryDragHandlers( node ) }
				>
					{ hasChildren ? (
						<button
							type="button"
							className="crnd-tree__toggle"
							aria-expanded={ isOpen }
							aria-label={
								isOpen
									? sprintf(
											/* translators: %s: category name */ __(
												'Collapse %s',
												'crnube-docs'
											),
											node.name
									  )
									: sprintf(
											/* translators: %s: category name */ __(
												'Expand %s',
												'crnube-docs'
											),
											node.name
									  )
							}
							onClick={ () => toggle( node.id ) }
						>
							<span
								className={ `dashicons dashicons-arrow-${
									isOpen ? 'down' : 'right'
								}-alt2` }
								aria-hidden="true"
							/>
						</button>
					) : (
						<span className="crnd-tree__toggle crnd-tree__toggle--empty" />
					) }
					<button
						type="button"
						className="crnd-tree__label"
						aria-current={
							selected === node.id ? 'true' : undefined
						}
						onClick={ () => onSelect( node.id ) }
					>
						<span
							className={ `dashicons dashicons-${
								isOpen && hasChildren
									? 'open-folder'
									: 'category'
							}` }
							aria-hidden="true"
						/>
						<span className="crnd-tree__name">{ node.name }</span>
						{ node.restricted && (
							<span
								className="crnd-tree__lock dashicons dashicons-lock"
								title={ __( 'Private', 'crnube-docs' ) }
							/>
						) }
						<span className="crnd-tree__count">{ node.total }</span>
					</button>
					<DropdownMenu
						className="crnd-tree__menu"
						icon="ellipsis"
						label={ sprintf(
							/* translators: %s: category name */ __(
								'Actions for %s',
								'crnube-docs'
							),
							node.name
						) }
						popoverProps={ { placement: 'bottom-end' } }
					>
						{ ( { onClose } ) => (
							<>
								<MenuGroup>
									<MenuItem
										icon="shortcode"
										onClick={ () => {
											onCopyShortcode( node );
											onClose();
										} }
									>
										{ __(
											'Copy shortcode',
											'crnube-docs'
										) }
									</MenuItem>
									{ extraActions.map( ( action ) => (
										<MenuItem
											key={ action.id }
											icon={ action.icon }
											onClick={ () => {
												onExtraAction( action, node );
												onClose();
											} }
										>
											{ action.label }
										</MenuItem>
									) ) }
								</MenuGroup>
								{ canManage && (
									<>
										<MenuGroup>
											<MenuItem
												icon="plus"
												onClick={ () => {
													onAdd( node.id );
													onClose();
												} }
											>
												{ __(
													'Add subcategory',
													'crnube-docs'
												) }
											</MenuItem>
											<MenuItem
												icon="edit"
												onClick={ () => {
													onRename( node );
													onClose();
												} }
											>
												{ __(
													'Rename or move',
													'crnube-docs'
												) }
											</MenuItem>
											<MenuItem
												icon="arrow-up-alt"
												disabled={ index === 0 }
												onClick={ () => {
													onMove(
														node,
														siblings,
														-1
													);
													onClose();
												} }
											>
												{ __(
													'Move up',
													'crnube-docs'
												) }
											</MenuItem>
											<MenuItem
												icon="arrow-down-alt"
												disabled={
													index ===
													siblings.length - 1
												}
												onClick={ () => {
													onMove( node, siblings, 1 );
													onClose();
												} }
											>
												{ __(
													'Move down',
													'crnube-docs'
												) }
											</MenuItem>
										</MenuGroup>
										<MenuGroup>
											<MenuItem
												icon="trash"
												isDestructive
												onClick={ () => {
													onDelete( node );
													onClose();
												} }
											>
												{ __(
													'Delete',
													'crnube-docs'
												) }
											</MenuItem>
										</MenuGroup>
									</>
								) }
							</>
						) }
					</DropdownMenu>
				</div>
				{ hasChildren && isOpen && (
					<ul className="crnd-tree__list" role="group">
						{ node.children.map( ( child, i ) =>
							renderNode( child, i, node.children )
						) }
					</ul>
				) }
			</li>
		);
	};

	const special = ( id, icon, label, count ) => (
		<div
			className={ [
				'crnd-tree__row',
				'crnd-tree__row--special',
				selected === id && 'is-selected',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<button
				type="button"
				className="crnd-tree__label"
				aria-current={ selected === id ? 'true' : undefined }
				onClick={ () => onSelect( id ) }
			>
				<span
					className={ `dashicons dashicons-${ icon }` }
					aria-hidden="true"
				/>
				<span className="crnd-tree__name">{ label }</span>
				<span className="crnd-tree__count">{ count }</span>
			</button>
		</div>
	);

	return (
		<nav
			className="crnd-tree"
			aria-label={ __( 'Categories', 'crnube-docs' ) }
		>
			<div className="crnd-tree__header">
				<h2>{ __( 'Categories', 'crnube-docs' ) }</h2>
				{ canManage && (
					<Button
						size="small"
						variant="tertiary"
						icon="plus-alt2"
						onClick={ () => onAdd( 0 ) }
					>
						{ __( 'New', 'crnube-docs' ) }
					</Button>
				) }
			</div>

			{ special(
				0,
				'portfolio',
				__( 'All documents', 'crnube-docs' ),
				total
			) }

			{ tree.length > 0 ? (
				<ul
					className="crnd-tree__list crnd-tree__list--root"
					role="tree"
				>
					{ tree.map( ( node, i ) => renderNode( node, i, tree ) ) }
				</ul>
			) : (
				<p className="crnd-tree__empty">
					{ canManage
						? __(
								'Create the first category, for example "Financial statements".',
								'crnube-docs'
						  )
						: __( 'There are no categories yet.', 'crnube-docs' ) }
				</p>
			) }

			{ uncategorized > 0 &&
				special(
					-1,
					'warning',
					__( 'Without category', 'crnube-docs' ),
					uncategorized
				) }

			<p className="crnd-tree__hint">
				{ __(
					'Tip: drag documents or files onto a category, and drag categories to reorder them or put one inside another.',
					'crnube-docs'
				) }
			</p>
		</nav>
	);
}
