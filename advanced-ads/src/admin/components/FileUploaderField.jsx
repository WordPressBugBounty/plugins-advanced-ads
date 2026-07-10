/**
 * External Dependencies
 */
import { Upload } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { useFileUploader } from '../hooks/useFileUploader';

export function FileUploaderField( {
	maxFiles = 5,
	maxSizeMB = 5,
	allowedExt = [ 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'docx', 'log' ],
	label = 'Drag & drop files or <strong>click to browse</strong>',
	uploaderRef = null, // pass a ref if parent needs { selectedFiles, reset }
} ) {
	const {
		selectedFiles,
		errors,
		atLimit,
		processFiles,
		deleteFile,
		reset,
		meta,
	} = useFileUploader( { maxFiles, maxSizeMB, allowedExt } );

	// Only expose selectedFiles + reset to the parent form via ref
	// Do NOT destructure and store the whole hook return — it goes stale
	if ( uploaderRef ) {
		uploaderRef.current = { selectedFiles, reset };
	}

	function handleDragOver( e ) {
		e.preventDefault();
		if ( ! atLimit ) {
			e.currentTarget.classList.add( 'dragover' );
		}
	}
	function handleDragLeave( e ) {
		e.currentTarget.classList.remove( 'dragover' );
	}
	function handleDrop( e ) {
		e.preventDefault();
		e.currentTarget.classList.remove( 'dragover' );
		processFiles( e.dataTransfer.files );
	}
	function handleChange( e ) {
		processFiles( e.target.files );
		e.target.value = ''; // allow re-selecting the same file
	}

	const count = selectedFiles.length;
	const acceptAttr = meta.allowedExt.map( ( e ) => '.' + e ).join( ',' );

	return (
		<div
			id="attachments-container"
			className="advads-field-input advads-file-uploader"
		>
			{ /* Drop zone */ }
			<div
				id="attachments-drop-zone"
				className={ `dropzone${ atLimit ? ' limit-reached' : '' }` }
				onDragOver={ handleDragOver }
				onDragLeave={ handleDragLeave }
				onDrop={ handleDrop }
			>
				<Upload className="size-8 mb-4" />

				<p
					className="text-sm mt-0 mb-2"
					dangerouslySetInnerHTML={ { __html: label } }
				/>

				<input
					id="attachments"
					type="file"
					multiple
					accept={ acceptAttr }
					onChange={ handleChange }
				/>

				{ /* Hints */ }
				<div className="upload-hints">
					<div>
						<strong>Allowed:</strong>{ ' ' }
						{ meta.allowedExt.map( ( e ) => '.' + e ).join( ', ' ) }
					</div>
					<span>
						<strong>Max size:</strong> { meta.maxSizeMB } MB per
						file
					</span>
					<span>
						<strong>Max files:</strong> { meta.maxFiles }
					</span>
				</div>
			</div>

			{ /* Count badge */ }
			{ count > 0 && (
				<div
					className={ `file-count-badge${
						atLimit ? ' at-limit' : ''
					}` }
				>
					{ count } / { meta.maxFiles } file{ count !== 1 ? 's' : '' }{ ' ' }
					selected
				</div>
			) }

			{ /* Errors */ }
			{ errors.length > 0 && (
				<div className="error-box">
					<p className="error-box-title">Some files were rejected:</p>
					<ul className="error-list">
						{ errors.map( ( r, i ) => (
							<li key={ i }>
								<strong>{ r.name }</strong>:{ ' ' }
								{ r.reasons.join( '; ' ) }
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ /* Selected files */ }
			<div className="selected-files-container">
				{ selectedFiles.map( ( f ) => (
					<div key={ f.id } className="file-item">
						<div className="file-image">
							{ f.isImage ? (
								<img src={ f.fileimage } alt="" />
							) : (
								<i className="far fa-file-alt" />
							) }
						</div>
						<div className="file-detail">
							<h6>{ f.filename }</h6>
							<p>
								<span>Size: { f.filesize }</span>
								<span style={ { marginLeft: 10 } }>
									Modified: { f.datetime }
								</span>
							</p>
							<div className="file-actions">
								<button
									className="file-action-btn"
									onClick={ () => deleteFile( f.id ) }
								>
									Delete
								</button>
							</div>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
}
