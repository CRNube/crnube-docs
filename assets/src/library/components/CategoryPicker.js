import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Dropdown } from '@wordpress/components';
import { flatten } from '../utils';

/**
 * The categories of one document as chips; clicking them opens a checkbox tree to
 * change them.
 */
export default function CategoryPicker( { value, tree, byId, onChange } ) {
	const options = flatten( tree );

	return (
		<Dropdown
			className="crnd-catpicker"
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<button
					type="button"
					className="crnd-chips"
					aria-expanded={ isOpen }
					onClick={ onToggle }
				>
					{ value.length === 0 && (
						<span className="crnd-chip crnd-chip--empty">
							{ __( 'No category', 'crnube-docs' ) }
						</span>
					) }
					{ value.map( ( id ) =>
						byId.has( id ) ? (
							<span key={ id } className="crnd-chip">
								{ byId.get( id ).name }
							</span>
						) : null
					) }
				</button>
			) }
			renderContent={ ( { onClose } ) => (
				<Picker
					options={ options }
					initial={ value }
					onCancel={ onClose }
					onSave={ ( ids ) => {
						onClose();
						onChange( ids );
					} }
				/>
			) }
		/>
	);
}

function Picker( { options, initial, onSave, onCancel } ) {
	const [ checked, setChecked ] = useState( () => new Set( initial ) );
	const toggle = ( id, on ) =>
		setChecked( ( current ) => {
			const next = new Set( current );
			if ( on ) {
				next.add( id );
			} else {
				next.delete( id );
			}
			return next;
		} );

	return (
		<div className="crnd-catpicker__content">
			<div className="crnd-catpicker__list">
				{ options.length === 0 && (
					<p>
						{ __( 'There are no categories yet.', 'crnube-docs' ) }
					</p>
				) }
				{ options.map( ( option ) => (
					<div
						key={ option.id }
						style={ { paddingInlineStart: option.depth * 16 } }
					>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ option.name }
							checked={ checked.has( option.id ) }
							onChange={ ( on ) => toggle( option.id, on ) }
						/>
					</div>
				) ) }
			</div>
			<div className="crnd-catpicker__actions">
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'crnube-docs' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => onSave( [ ...checked ] ) }
				>
					{ __( 'Apply', 'crnube-docs' ) }
				</Button>
			</div>
		</div>
	);
}
