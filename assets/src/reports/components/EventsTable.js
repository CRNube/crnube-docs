import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { dimensionLabel, formatDateTime } from '../format';

/** Each view and download of the period, newest first. */
export default function EventsTable( {
	events,
	page,
	pages,
	loading,
	onPage,
	onDocument,
} ) {
	return (
		<section className="crnd-panel crnd-panel--wide">
			<h2 className="crnd-panel__title">
				{ __( 'Activity log', 'crnube-docs' ) }
				{ loading && <Spinner /> }
			</h2>
			{ events.length === 0 ? (
				<p className="crnd-panel__empty">
					{ __(
						'No views or downloads in this period.',
						'crnube-docs'
					) }
				</p>
			) : (
				<div className="crnd-events-wrap">
					<table className="crnd-events">
						<thead>
							<tr>
								<th>
									{ __( 'Date and time', 'crnube-docs' ) }
								</th>
								<th>{ __( 'Document', 'crnube-docs' ) }</th>
								<th>{ __( 'Action', 'crnube-docs' ) }</th>
								<th>{ __( 'Device', 'crnube-docs' ) }</th>
								<th>{ __( 'Browser', 'crnube-docs' ) }</th>
								<th>{ __( 'System', 'crnube-docs' ) }</th>
								<th>{ __( 'Country', 'crnube-docs' ) }</th>
								<th>{ __( 'Came from', 'crnube-docs' ) }</th>
								<th>{ __( 'User', 'crnube-docs' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ events.map( ( e ) => (
								<tr key={ e.id }>
									<td className="crnd-nowrap">
										{ formatDateTime( e.date ) }
									</td>
									<td>
										<button
											type="button"
											className="crnd-link"
											onClick={ () =>
												onDocument(
													e.document_id,
													e.title
												)
											}
										>
											{ e.title }
										</button>
									</td>
									<td>
										<span
											className={ `crnd-action crnd-action--${ e.event }` }
										>
											{ e.event === 'view'
												? __( 'Viewed', 'crnube-docs' )
												: __(
														'Downloaded',
														'crnube-docs'
												  ) }
										</span>
									</td>
									<td>
										{ dimensionLabel( 'device', e.device ) }
									</td>
									<td>
										{ dimensionLabel(
											'browser',
											e.browser
										) }
									</td>
									<td>{ dimensionLabel( 'os', e.os ) }</td>
									<td>
										{ dimensionLabel(
											'country',
											e.country
										) }
									</td>
									<td
										className="crnd-source"
										title={ e.source }
									>
										{ e.source || '—' }
									</td>
									<td>{ e.user || '—' }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
			{ pages > 1 && (
				<nav className="crnd-pager">
					<Button
						variant="secondary"
						icon="arrow-left-alt2"
						disabled={ page <= 1 }
						label={ __( 'Previous page', 'crnube-docs' ) }
						onClick={ () => onPage( page - 1 ) }
					/>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages */
							__( 'Page %1$d of %2$d', 'crnube-docs' ),
							page,
							pages
						) }
					</span>
					<Button
						variant="secondary"
						icon="arrow-right-alt2"
						disabled={ page >= pages }
						label={ __( 'Next page', 'crnube-docs' ) }
						onClick={ () => onPage( page + 1 ) }
					/>
				</nav>
			) }
		</section>
	);
}
