import { useState, useCallback, useRef } from 'react';

function generateId() {
	return (
		Math.random().toString( 36 ).slice( 2, 9 ) + Date.now().toString( 36 )
	);
}

function formatSize( bytes, decimals = 2 ) {
	if ( bytes === 0 ) {
		return '0 Bytes';
	}
	const k = 1024;
	const sizes = [ 'Bytes', 'KB', 'MB', 'GB', 'TB' ];
	const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
	return (
		parseFloat(
			( bytes / Math.pow( k, i ) ).toFixed( Math.max( 0, decimals ) )
		) +
		' ' +
		sizes[ i ]
	);
}

function isImageFile( filename ) {
	return /\.(jpg|jpeg|png|gif|svg)$/i.test( filename );
}

function getExt( filename ) {
	return filename.split( '.' ).pop().toLowerCase();
}

export function useFileUploader( {
	maxFiles = 10,
	maxSizeMB = 5,
	allowedExt = [ 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'docx', 'txt' ],
} = {} ) {
	const [ selectedFiles, setSelectedFiles ] = useState( [] );
	const [ errors, setErrors ] = useState( [] );

	// Tracks committed + in-flight count so slots never goes stale between async reads
	const totalCountRef = useRef( 0 );

	const syncFiles = ( updater ) => {
		setSelectedFiles( ( prev ) => {
			const next =
				typeof updater === 'function' ? updater( prev ) : updater;
			totalCountRef.current = next.length;
			return next;
		} );
	};

	const maxSizeBytes = maxSizeMB * 1024 * 1024;

	const validateFile = useCallback(
		( file ) => {
			const errs = [];
			const ext = getExt( file.name );
			if ( ! allowedExt.includes( ext ) ) {
				errs.push( `".${ ext }" is not an allowed extension` );
			}
			if ( file.size > maxSizeBytes ) {
				errs.push(
					`exceeds max size of ${ maxSizeMB } MB (is ${ formatSize(
						file.size
					) })`
				);
			}
			return errs;
		},
		[ allowedExt, maxSizeBytes, maxSizeMB ]
	);

	const processFiles = useCallback(
		( fileList ) => {
			const slots = maxFiles - totalCountRef.current;

			if ( slots <= 0 ) {
				setErrors( [
					{
						name: 'Selection blocked',
						reasons: [
							`you have already reached the limit of ${ maxFiles } files`,
						],
					},
				] );
				return;
			}

			const rejections = [];
			let accepted = 0;

			for ( const file of fileList ) {
				if ( accepted >= slots ) {
					rejections.push( {
						name: file.name,
						reasons: [ `max file limit of ${ maxFiles } reached` ],
					} );
					continue;
				}

				const errs = validateFile( file );
				if ( errs.length ) {
					rejections.push( { name: file.name, reasons: errs } );
					continue;
				}

				// Increment ref immediately — before the async read completes —
				// so the next processFiles call sees the correct count right away
				totalCountRef.current += 1;

				const reader = new FileReader();
				reader.onloadend = () => {
					syncFiles( ( prev ) => [
						...prev,
						{
							id: generateId(),
							filename: file.name,
							filetype: file.type,
							fileimage: reader.result,
							datetime: file.lastModified
								? new Date( file.lastModified ).toLocaleString(
										'en-IN'
								  )
								: 'Unknown',
							filesize: formatSize( file.size ),
							isImage: isImageFile( file.name ),
						},
					] );
				};
				reader.readAsDataURL( file );
				accepted++;
			}

			setErrors( rejections );
		},
		[ maxFiles, validateFile ]
	);

	const deleteFile = useCallback( ( id ) => {
		if (
			globalThis.confirm( 'Are you sure you want to delete this file?' )
		) {
			syncFiles( ( prev ) => prev.filter( ( f ) => f.id !== id ) );
		}
	}, [] );

	const reset = useCallback( () => {
		totalCountRef.current = 0;
		syncFiles( [] );
		setErrors( [] );
	}, [] );

	const atLimit = selectedFiles.length >= maxFiles;

	return {
		selectedFiles,
		errors,
		atLimit,
		processFiles,
		deleteFile,
		reset,
		meta: { maxFiles, maxSizeMB, allowedExt },
	};
}
