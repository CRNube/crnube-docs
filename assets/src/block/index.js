import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	ColorPalette,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';

const inherit = { value: '', label: __( 'As in the settings', 'crnube-docs' ) };
const yesNo = [
	inherit,
	{ value: 'yes', label: __( 'Yes', 'crnube-docs' ) },
	{ value: 'no', label: __( 'No', 'crnube-docs' ) },
];

/** Categories as an indented list: parents first, then their children. */
function useCategories() {
	return useSelect( ( select ) => {
		const terms = select( coreStore ).getEntityRecords(
			'taxonomy',
			'crnd_category',
			{
				per_page: 100,
				context: 'view',
			}
		);
		if ( ! terms ) {
			return null;
		}
		const byParent = new Map();
		terms.forEach( ( t ) => {
			if ( ! byParent.has( t.parent ) ) {
				byParent.set( t.parent, [] );
			}
			byParent.get( t.parent ).push( t );
		} );
		const out = [];
		const walk = ( parent, depth ) =>
			( byParent.get( parent ) || [] )
				.sort( ( a, b ) => a.name.localeCompare( b.name ) )
				.forEach( ( t ) => {
					out.push( { id: t.id, name: t.name, depth } );
					walk( t.id, depth + 1 );
				} );
		walk( 0, 0 );
		return out;
	}, [] );
}

function Edit( { attributes, setAttributes } ) {
	const categories = useCategories();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	const toggleCategory = ( id, on ) => {
		const next = new Set( attributes.categories );
		if ( on ) {
			next.add( id );
		} else {
			next.delete( id );
		}
		setAttributes( { categories: [ ...next ] } );
	};

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Documents', 'crnube-docs' ) }>
					<p className="components-base-control__help">
						{ __( 'None checked: every category.', 'crnube-docs' ) }
					</p>
					{ categories === null && <Spinner /> }
					{ categories?.map( ( c ) => (
						<div
							key={ c.id }
							style={ { paddingInlineStart: c.depth * 14 } }
						>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ c.name }
								checked={ attributes.categories.includes(
									c.id
								) }
								onChange={ ( on ) =>
									toggleCategory( c.id, on )
								}
							/>
						</div>
					) ) }
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Title', 'crnube-docs' ) }
						value={ attributes.title }
						onChange={ set( 'title' ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Layout', 'crnube-docs' ) }
						value={ attributes.layout }
						options={ [
							inherit,
							{
								value: 'tree',
								label: __( 'Tree', 'crnube-docs' ),
							},
							{
								value: 'sections',
								label: __( 'Sections', 'crnube-docs' ),
							},
							{
								value: 'grid',
								label: __( 'Grid', 'crnube-docs' ),
							},
							{
								value: 'list',
								label: __( 'List', 'crnube-docs' ),
							},
						] }
						onChange={ set( 'layout' ) }
					/>
					{ attributes.layout === 'grid' && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Grid columns', 'crnube-docs' ) }
							value={ attributes.columns }
							options={ [
								inherit,
								...[ '2', '3', '4' ].map( ( n ) => ( {
									value: n,
									label: n,
								} ) ),
							] }
							onChange={ set( 'columns' ) }
						/>
					) }
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Groups open on load', 'crnube-docs' ) }
						value={ attributes.open }
						options={ [
							inherit,
							{
								value: 'none',
								label: __( 'All closed', 'crnube-docs' ),
							},
							{
								value: 'first',
								label: __( 'First one open', 'crnube-docs' ),
							},
							{
								value: 'all',
								label: __( 'All open', 'crnube-docs' ),
							},
						] }
						onChange={ set( 'open' ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Display', 'crnube-docs' ) }
					initialOpen={ false }
				>
					{ [
						[ 'search', __( 'Search box', 'crnube-docs' ) ],
						[ 'yearFilter', __( 'Year filter', 'crnube-docs' ) ],
						[
							'toggleAll',
							__(
								'Expand / collapse all buttons',
								'crnube-docs'
							),
						],
						[
							'counts',
							__( 'Document count on each group', 'crnube-docs' ),
						],
						[
							'groupByYear',
							__(
								'Group documents by year inside each category',
								'crnube-docs'
							),
						],
						[ 'date', __( 'Publication date', 'crnube-docs' ) ],
						[ 'size', __( 'File size', 'crnube-docs' ) ],
						[ 'type', __( 'File type', 'crnube-docs' ) ],
					].map( ( [ key, label ] ) => (
						<SelectControl
							key={ key }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ label }
							value={ attributes[ key ] }
							options={ yesNo }
							onChange={ set( key ) }
						/>
					) ) }
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Restricted documents in public libraries',
							'crnube-docs'
						) }
						value={ attributes.private }
						options={ [
							inherit,
							{
								value: 'lock',
								label: __(
									'Show them with a lock and "Sign in"',
									'crnube-docs'
								),
							},
							{
								value: 'hide',
								label: __(
									'Hide them from people without access',
									'crnube-docs'
								),
							},
						] }
						onChange={ set( 'private' ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Design', 'crnube-docs' ) }
					initialOpen={ false }
				>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Group header style', 'crnube-docs' ) }
						value={ attributes.headerStyle }
						options={ [
							inherit,
							{
								value: 'soft',
								label: __( 'Soft', 'crnube-docs' ),
							},
							{
								value: 'solid',
								label: __( 'Solid', 'crnube-docs' ),
							},
							{
								value: 'minimal',
								label: __( 'Minimal', 'crnube-docs' ),
							},
						] }
						onChange={ set( 'headerStyle' ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Density', 'crnube-docs' ) }
						value={ attributes.density }
						options={ [
							inherit,
							{
								value: 'comfortable',
								label: __( 'Comfortable', 'crnube-docs' ),
							},
							{
								value: 'compact',
								label: __( 'Compact', 'crnube-docs' ),
							},
						] }
						onChange={ set( 'density' ) }
					/>
					<p>{ __( 'Primary', 'crnube-docs' ) }</p>
					<ColorPalette
						value={ attributes.primary || undefined }
						onChange={ ( c ) =>
							setAttributes( { primary: c || '' } )
						}
						disableCustomColors={ false }
						clearable
					/>
					<p>{ __( 'Accent', 'crnube-docs' ) }</p>
					<ColorPalette
						value={ attributes.accent || undefined }
						onChange={ ( c ) =>
							setAttributes( { accent: c || '' } )
						}
						clearable
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="crnube/documents"
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( 'crnube/documents', {
	apiVersion: 3,
	title: __( 'Document library', 'crnube-docs' ),
	description: __(
		'Documents with collapsible categories, search and year filter.',
		'crnube-docs'
	),
	category: 'widgets',
	icon: 'media-document',
	keywords: [ 'documentos', 'pdf', 'informes', 'crnube' ],
	supports: { align: [ 'wide', 'full' ], html: false },
	edit: Edit,
	save: () => null,
} );
