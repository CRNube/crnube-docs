import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import FileIcon from './FileIcon';
import { extensionOf } from '../utils';

/**
 * Progress of the files being uploaded, bottom right.
 */
export default function UploadPanel( { uploads, onDismiss } ) {
	if ( uploads.length === 0 ) {
		return null;
	}
	const pending = uploads.filter(
		( u ) => u.state === 'queued' || u.state === 'uploading'
	).length;
	const failed = uploads.filter( ( u ) => u.state === 'error' ).length;

	let title = __( 'Upload complete', 'crnube-docs' );
	if ( pending > 0 ) {
		title = sprintf(
			/* translators: %d: number of files still uploading */
			_n(
				'Uploading %d file…',
				'Uploading %d files…',
				pending,
				'crnube-docs'
			),
			pending
		);
	} else if ( failed > 0 ) {
		title = sprintf(
			/* translators: %d: number of files that failed */
			_n(
				'%d file could not be uploaded',
				'%d files could not be uploaded',
				failed,
				'crnube-docs'
			),
			failed
		);
	}

	return (
		<section className="crnd-uploads" aria-live="polite">
			<header className="crnd-uploads__header">
				<strong>{ title }</strong>
				{ pending === 0 && (
					<Button
						size="small"
						icon="no-alt"
						label={ __( 'Close', 'crnube-docs' ) }
						onClick={ onDismiss }
					/>
				) }
			</header>
			<ul className="crnd-uploads__list">
				{ uploads.map( ( upload ) => {
					const ext = extensionOf( upload.name );
					return (
						<li
							key={ upload.key }
							className={ `crnd-upload crnd-upload--${ upload.state }` }
						>
							<FileIcon
								kind={ upload.doc?.kind || 'other' }
								ext={ ext }
							/>
							<div className="crnd-upload__body">
								<span className="crnd-upload__name">
									{ upload.doc?.title || upload.name }
								</span>
								{ upload.state === 'error' ? (
									<span className="crnd-upload__error">
										{ upload.error }
									</span>
								) : (
									<span className="crnd-upload__bar">
										<span
											style={ {
												width: `${ Math.round(
													upload.progress * 100
												) }%`,
											} }
										/>
									</span>
								) }
							</div>
							<span
								className="crnd-upload__state"
								aria-hidden="true"
							>
								{ upload.state === 'done' && (
									<span className="dashicons dashicons-yes-alt" />
								) }
								{ upload.state === 'error' && (
									<span className="dashicons dashicons-warning" />
								) }
							</span>
						</li>
					);
				} ) }
			</ul>
		</section>
	);
}
