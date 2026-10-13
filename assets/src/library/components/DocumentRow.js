import { __, sprintf } from '@wordpress/i18n';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import FileIcon from './FileIcon';
import InlineEdit from './InlineEdit';
import CategoryPicker from './CategoryPicker';
import Checkbox from './Checkbox';
import { config, formatSize, statusLabel } from '../utils';

/**
 * One document in the library table. When the list can be reordered it gets a drag
 * handle and "move" actions in its menu (the keyboard alternative to dragging).
 */
export default function DocumentRow( {
	doc,
	index,
	count,
	checked,
	onToggle,
	tree,
	byId,
	reorderable,
	dropIndicator,
	dragProps,
	onMove,
	onSave,
	onTrash,
	onReplace,
} ) {
	const fileLine = doc.file
		? [ doc.file.name, formatSize( doc.file.size ) ]
				.filter( Boolean )
				.join( ' · ' )
		: doc.external_url || __( 'No file', 'crnube-docs' );

	const className = [
		checked && 'is-checked',
		dropIndicator && `is-drop-${ dropIndicator }`,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<tr className={ className } { ...dragProps }>
			{ reorderable && (
				<td className="crnd-col-handle">
					<span
						className="crnd-handle dashicons dashicons-menu"
						title={ __(
							'Drag to change the order',
							'crnube-docs'
						) }
						aria-hidden="true"
					/>
				</td>
			) }
			<td className="crnd-col-check">
				<Checkbox
					label={ sprintf(
						/* translators: %s: document title */
						__( 'Select %s', 'crnube-docs' ),
						doc.title
					) }
					checked={ checked }
					onChange={ onToggle }
				/>
			</td>
			<td className="crnd-col-title">
				<div className="crnd-doc-cell">
					<FileIcon
						kind={ doc.kind }
						ext={ doc.ext }
						link={ ! doc.file && !! doc.external_url }
					/>
					<div className="crnd-doc-cell__text">
						<InlineEdit
							className="crnd-doc-cell__title"
							label={ __( 'Title', 'crnube-docs' ) }
							value={ doc.title }
							validate={ ( v ) => v !== '' }
							onSave={ ( title ) => onSave( doc.id, { title } ) }
						/>
						<span className="crnd-doc-cell__file">
							{ fileLine }
						</span>
					</div>
				</div>
			</td>
			<td className="crnd-col-cats">
				<CategoryPicker
					value={ doc.categories }
					tree={ tree }
					byId={ byId }
					onChange={ ( categories ) =>
						onSave( doc.id, { categories } )
					}
				/>
			</td>
			<td className="crnd-col-year">
				<InlineEdit
					type="number"
					label={ __( 'Document year', 'crnube-docs' ) }
					value={ doc.year_own }
					placeholder={ doc.year }
					validate={ ( v ) => v === '' || /^\d{4}$/.test( v ) }
					display={
						<span
							className={ doc.year_own ? '' : 'crnd-muted' }
							title={
								doc.year_own
									? ''
									: __( 'Publication year', 'crnube-docs' )
							}
						>
							{ doc.year }
						</span>
					}
					onSave={ ( year ) => onSave( doc.id, { year } ) }
				/>
			</td>
			<td className="crnd-col-status">
				<button
					type="button"
					className={ `crnd-status crnd-status--${ doc.status }` }
					disabled={
						! config.canPublish ||
						! [ 'publish', 'draft' ].includes( doc.status )
					}
					title={
						doc.status === 'publish'
							? __( 'Click to unpublish (draft)', 'crnube-docs' )
							: __( 'Click to publish', 'crnube-docs' )
					}
					onClick={ () =>
						onSave( doc.id, {
							status:
								doc.status === 'publish' ? 'draft' : 'publish',
						} )
					}
				>
					{ statusLabel( doc.status ) }
				</button>
			</td>
			<td className="crnd-col-date">{ doc.date_label }</td>
			<td
				className="crnd-col-downloads"
				title={ sprintf(
					/* translators: 1: number of downloads, 2: number of views */
					__( '%1$s downloads · %2$s views', 'crnube-docs' ),
					doc.downloads.toLocaleString(),
					doc.views.toLocaleString()
				) }
			>
				{ ( doc.downloads + doc.views ).toLocaleString() }
			</td>
			<td className="crnd-col-actions">
				<DropdownMenu
					icon="ellipsis"
					label={ sprintf(
						/* translators: %s: document title */
						__( 'Actions for %s', 'crnube-docs' ),
						doc.title
					) }
					popoverProps={ { placement: 'bottom-end' } }
				>
					{ ( { onClose } ) => (
						<>
							{ reorderable && (
								<MenuGroup
									label={ __( 'Order', 'crnube-docs' ) }
								>
									<MenuItem
										icon="arrow-up-alt"
										disabled={ index === 0 }
										onClick={ () => {
											onClose();
											onMove( 'top' );
										} }
									>
										{ __(
											'Move to the top',
											'crnube-docs'
										) }
									</MenuItem>
									<MenuItem
										icon="arrow-up-alt2"
										disabled={ index === 0 }
										onClick={ () => {
											onClose();
											onMove( 'up' );
										} }
									>
										{ __( 'Move up', 'crnube-docs' ) }
									</MenuItem>
									<MenuItem
										icon="arrow-down-alt2"
										disabled={ index === count - 1 }
										onClick={ () => {
											onClose();
											onMove( 'down' );
										} }
									>
										{ __( 'Move down', 'crnube-docs' ) }
									</MenuItem>
									<MenuItem
										icon="arrow-down-alt"
										disabled={ index === count - 1 }
										onClick={ () => {
											onClose();
											onMove( 'bottom' );
										} }
									>
										{ __(
											'Move to the bottom',
											'crnube-docs'
										) }
									</MenuItem>
								</MenuGroup>
							) }
							<MenuGroup>
								{ ( doc.file || doc.external_url ) && (
									<MenuItem
										icon="visibility"
										href={ doc.view_url }
										target="_blank"
										rel="noopener"
										onClick={ onClose }
									>
										{ __( 'Open', 'crnube-docs' ) }
									</MenuItem>
								) }
								{ config.canUpload && (
									<MenuItem
										icon="upload"
										onClick={ () => {
											onClose();
											onReplace();
										} }
									>
										{ __( 'Replace file', 'crnube-docs' ) }
									</MenuItem>
								) }
								<MenuItem icon="edit" href={ doc.edit_url }>
									{ __( 'Edit all details', 'crnube-docs' ) }
								</MenuItem>
								{ config.reportsUrl && (
									<MenuItem
										icon="chart-bar"
										href={ `${ config.reportsUrl }&document=${ doc.id }` }
									>
										{ __(
											'See statistics',
											'crnube-docs'
										) }
									</MenuItem>
								) }
							</MenuGroup>
							{ doc.can_delete && (
								<MenuGroup>
									<MenuItem
										icon="trash"
										isDestructive
										onClick={ () => {
											onClose();
											onTrash( doc );
										} }
									>
										{ __( 'Move to trash', 'crnube-docs' ) }
									</MenuItem>
								</MenuGroup>
							) }
						</>
					) }
				</DropdownMenu>
			</td>
		</tr>
	);
}
