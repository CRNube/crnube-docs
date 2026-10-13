import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, TextControl, TreeSelect } from '@wordpress/components';
import { toTreeSelect } from '../utils';

/**
 * Create a category, or rename / move an existing one.
 */
export function CategoryModal( { category, parent, tree, onSave, onClose } ) {
	const [ name, setName ] = useState( category?.name || '' );
	const [ parentId, setParentId ] = useState(
		String( category ? category.parent : parent || 0 )
	);

	// A category cannot be moved inside itself, so its own branch is left out.
	const prune = ( nodes ) =>
		nodes
			.filter( ( node ) => node.id !== category?.id )
			.map( ( node ) => ( {
				...node,
				children: prune( node.children ),
			} ) );

	const submit = ( event ) => {
		event.preventDefault();
		if ( name.trim() !== '' ) {
			onSave( { name: name.trim(), parent: Number( parentId ) || 0 } );
		}
	};

	return (
		<Modal
			title={
				category
					? __( 'Edit category', 'crnube-docs' )
					: __( 'New category', 'crnube-docs' )
			}
			onRequestClose={ onClose }
			size="small"
		>
			<form onSubmit={ submit } className="crnd-modal-form">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Name', 'crnube-docs' ) }
					value={ name }
					onChange={ setName }
					placeholder={ __(
						'e.g. Audited financial statements',
						'crnube-docs'
					) }
					autoFocus // eslint-disable-line jsx-a11y/no-autofocus
				/>
				<TreeSelect
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Inside', 'crnube-docs' ) }
					noOptionLabel={ __( '— Top level —', 'crnube-docs' ) }
					tree={ toTreeSelect( prune( tree ) ) }
					selectedId={ parentId === '0' ? '' : parentId }
					onChange={ ( id ) => setParentId( id || '0' ) }
				/>
				<div className="crnd-modal-actions">
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'crnube-docs' ) }
					</Button>
					<Button
						variant="primary"
						type="submit"
						disabled={ name.trim() === '' }
					>
						{ category
							? __( 'Save', 'crnube-docs' )
							: __( 'Create', 'crnube-docs' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}

export function ConfirmModal( {
	title,
	message,
	confirmLabel,
	onConfirm,
	onClose,
} ) {
	return (
		<Modal title={ title } onRequestClose={ onClose } size="small">
			<p>{ message }</p>
			<div className="crnd-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'crnube-docs' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={ () => {
						onClose();
						onConfirm();
					} }
				>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
