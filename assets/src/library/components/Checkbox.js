/**
 * A plain checkbox with an accessible name only: the table rows have no room for labels.
 */
export default function Checkbox( {
	label,
	checked,
	indeterminate = false,
	onChange,
} ) {
	return (
		<input
			type="checkbox"
			className="crnd-check"
			aria-label={ label }
			checked={ checked }
			ref={ ( el ) => {
				if ( el ) {
					el.indeterminate = indeterminate;
				}
			} }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	);
}
