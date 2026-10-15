/**
 * Same file badge as the public library (src/Frontend/Icons.php).
 */
const COLORS = {
	pdf: '#e5484d',
	word: '#2b6cd9',
	excel: '#1f9d55',
	powerpoint: '#e86a1c',
	archive: '#7c6f64',
	image: '#8e4ec6',
	text: '#5b6b7f',
	other: '#5b6b7f',
};

export default function FileIcon( { kind, ext, link = false } ) {
	const color = COLORS[ kind ] || COLORS.other;
	if ( link ) {
		return (
			<span
				className="crnd-lib-icon crnd-lib-icon--link dashicons dashicons-admin-links"
				aria-hidden="true"
			/>
		);
	}
	const label = ( ext || 'doc' ).toUpperCase().slice( 0, 4 );
	return (
		<svg
			className="crnd-lib-icon"
			viewBox="0 0 32 40"
			width="28"
			height="35"
			aria-hidden="true"
			focusable="false"
		>
			<path
				d="M4 0h17l11 11v25a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z"
				fill={ color }
				opacity=".14"
			/>
			<path d="M21 0v7a4 4 0 0 0 4 4h7z" fill={ color } opacity=".35" />
			<rect x="0" y="21" width="28" height="13" rx="3" fill={ color } />
			<text
				x="14"
				y="30.5"
				textAnchor="middle"
				fontFamily="system-ui,sans-serif"
				fontSize={ label.length > 3 ? 7 : 8.5 }
				fontWeight="700"
				fill="#fff"
			>
				{ label }
			</text>
		</svg>
	);
}
