import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Text that turns into an input when clicked. Enter or leaving the field saves,
 * Escape cancels.
 */
export default function InlineEdit( {
	value,
	onSave,
	label,
	type = 'text',
	placeholder = '',
	className = '',
	display,
	validate = () => true,
} ) {
	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( value );
	const input = useRef();

	useEffect( () => {
		if ( editing ) {
			input.current?.focus();
			input.current?.select();
		}
	}, [ editing ] );

	const commit = () => {
		setEditing( false );
		const next = String( draft ).trim();
		if ( next !== String( value ) && validate( next ) ) {
			onSave( next );
		} else {
			setDraft( value );
		}
	};

	if ( editing ) {
		return (
			<input
				ref={ input }
				type={ type }
				className={ `crnd-inline-input ${ className }` }
				aria-label={ label }
				value={ draft }
				placeholder={ placeholder }
				onChange={ ( event ) => setDraft( event.target.value ) }
				onBlur={ commit }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' ) {
						event.preventDefault();
						commit();
					} else if ( event.key === 'Escape' ) {
						setDraft( value );
						setEditing( false );
					}
				} }
			/>
		);
	}

	return (
		<button
			type="button"
			className={ `crnd-inline ${ className }` }
			title={ __( 'Click to edit', 'crnube-docs' ) }
			aria-label={ `${ label }: ${ value || placeholder }` }
			onClick={ () => {
				setDraft( value );
				setEditing( true );
			} }
		>
			{ display ??
				( value || (
					<span className="crnd-inline__placeholder">
						{ placeholder }
					</span>
				) ) }
		</button>
	);
}
