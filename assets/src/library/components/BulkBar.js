import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, SelectControl, TreeSelect } from '@wordpress/components';
import { config, toTreeSelect } from '../utils';

/**
 * Actions for the selected documents.
 */
export default function BulkBar( { count, tree, onApply, onClear } ) {
	const [ action, setAction ] = useState( 'move' );
	const [ category, setCategory ] = useState( '' );
	const [ year, setYear ] = useState( '' );

	const needsCategory = [
		'move',
		'add_category',
		'remove_category',
	].includes( action );
	const ready = needsCategory
		? category !== ''
		: action !== 'set_year' || /^(\d{4})?$/.test( year );

	const actions = [
		{ value: 'move', label: __( 'Move to category', 'crnube-docs' ) },
		{
			value: 'add_category',
			label: __( 'Also add to category', 'crnube-docs' ),
		},
		{
			value: 'remove_category',
			label: __( 'Remove from category', 'crnube-docs' ),
		},
		{ value: 'set_year', label: __( 'Set document year', 'crnube-docs' ) },
		...( config.canPublish
			? [ { value: 'publish', label: __( 'Publish', 'crnube-docs' ) } ]
			: [] ),
		{ value: 'draft', label: __( 'Change to draft', 'crnube-docs' ) },
		{ value: 'trash', label: __( 'Move to trash', 'crnube-docs' ) },
	];

	return (
		<div
			className="crnd-bulk"
			role="region"
			aria-label={ __( 'Bulk actions', 'crnube-docs' ) }
		>
			<strong className="crnd-bulk__count">
				{ sprintf(
					/* translators: %d: number of selected documents */
					_n( '%d selected', '%d selected', count, 'crnube-docs' ),
					count
				) }
			</strong>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Action', 'crnube-docs' ) }
				hideLabelFromVision
				value={ action }
				options={ actions }
				onChange={ setAction }
			/>
			{ needsCategory && (
				<TreeSelect
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Category', 'crnube-docs' ) }
					hideLabelFromVision
					noOptionLabel={ __( 'Choose a category…', 'crnube-docs' ) }
					tree={ toTreeSelect( tree ) }
					selectedId={ category }
					onChange={ setCategory }
				/>
			) }
			{ action === 'set_year' && (
				<input
					type="number"
					className="crnd-bulk__year"
					aria-label={ __( 'Year', 'crnube-docs' ) }
					placeholder={ __(
						'Empty: publication year',
						'crnube-docs'
					) }
					min="1900"
					max="2100"
					value={ year }
					onChange={ ( event ) => setYear( event.target.value ) }
				/>
			) }
			<Button
				variant="primary"
				isDestructive={ action === 'trash' }
				disabled={ ! ready }
				onClick={ () =>
					onApply( action, needsCategory ? Number( category ) : year )
				}
			>
				{ __( 'Apply', 'crnube-docs' ) }
			</Button>
			<Button variant="tertiary" onClick={ onClear }>
				{ __( 'Clear selection', 'crnube-docs' ) }
			</Button>
		</div>
	);
}
