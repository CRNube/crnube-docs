/**
 * Extension points of the library app, for add-ons such as CRNube Docs Pro.
 *
 * An add-on enqueues its script on the `crnd_library_enqueue` PHP action and adds
 * filters with @wordpress/hooks before the page finishes loading:
 *
 *   addFilter( 'crnd.library.categoryActions', 'my-addon', ( actions ) => [
 *     ...actions,
 *     { id: 'mine', icon: 'lock', label: 'Do something…', render: ( props ) => <MyModal { ...props } /> },
 *   ] );
 *
 * - `crnd.library.categoryActions`: items of each category's menu. `render( props )`
 *   returns the modal to show, with props { category, close, setCategoryData,
 *   reloadDocuments, notifySuccess, notifyError }.
 * - `crnd.library.documentColumns`: extra columns of the document list, as
 *   { id, label, className, render( { doc, onSave } ) }. `onSave( doc.id, changes )`
 *   sends a PATCH to crnd/v1/documents/{id} (see the `crnd_rest_document_args` PHP
 *   filter).
 */
import { applyFilters } from '@wordpress/hooks';

export const categoryActions = () =>
	applyFilters( 'crnd.library.categoryActions', [] );

export const documentColumns = () =>
	applyFilters( 'crnd.library.documentColumns', [] );
