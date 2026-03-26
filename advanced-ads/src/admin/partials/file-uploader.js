/* eslint-disable no-alert */
class FileUploader {
	/**
	 * @param {HTMLElement}      wrap    Wrap element
	 * @param {HTMLInputElement} inputEl File input element (carries data-* config)
	 */
	constructor( wrap, inputEl ) {
		this.wrap = wrap;
		this.input = inputEl;
		this.ui = {
			dropZone: wrap.querySelector( '.dropzone' ),
		};

		// ── Read limits from data-attributes on the input ────────────
		this.maxSizeMB = parseFloat( inputEl.dataset.maxSizeMb ) || 5;
		this.maxFiles = parseInt( inputEl.dataset.maxFiles ) || 10;
		this.allowedExt = (
			inputEl.dataset.allowedExt || 'jpg,jpeg,png,gif,svg,pdf,docx,txt'
		)
			.split( ',' )
			.map( ( e ) => e.trim().toLowerCase() );
		// ─────────────────────────────────────────────────────────────

		this.maxSizeBytes = this.maxSizeMB * 1024 * 1024;

		// Internal state
		this.selectedFiles = [];

		// Setup
		this.input.accept = this.allowedExt.map( ( e ) => '.' + e ).join( ',' );
		this._createRegions();
		this._renderHints();
		this._bindEvents();
	}

	reset() {
		this.selectedFiles = [];
		this._renderSelected();
	}

	// ── Event binding ─────────────────────────────────────────────

	_bindEvents() {
		// Native file picker
		this.input.addEventListener( 'change', ( e ) => {
			this._processFiles( e.target.files );
			this.input.value = ''; // reset so same file can be re-selected
		} );

		// Drag & drop
		this.ui.dropZone.addEventListener( 'dragover', ( e ) => {
			e.preventDefault();
			if ( ! this.ui.dropZone.classList.contains( 'limit-reached' ) ) {
				this.ui.dropZone.classList.add( 'dragover' );
			}
		} );
		this.ui.dropZone.addEventListener( 'dragleave', () =>
			this.ui.dropZone.classList.remove( 'dragover' )
		);
		this.ui.dropZone.addEventListener( 'drop', ( e ) => {
			e.preventDefault();
			this.ui.dropZone.classList.remove( 'dragover' );
			this._processFiles( e.dataTransfer.files );
		} );
	}

	// ── UI Rendering ──────────────────────────────────────────────

	_createRegions() {
		// Count badge
		const countBadge = document.createElement( 'div' );
		countBadge.className = 'file-count-badge';
		countBadge.style.display = 'none';
		this.wrap.appendChild( countBadge );
		this.ui.countBadge = countBadge;

		// Error box
		const errorBox = document.createElement( 'div' );
		errorBox.className = 'error-box';
		errorBox.style.display = 'none';
		this.wrap.appendChild( errorBox );
		this.ui.errorBox = errorBox;

		// Error box title
		const errorBoxTitle = document.createElement( 'p' );
		errorBoxTitle.className = 'error-box-title';
		errorBoxTitle.innerHTML = 'Some files were rejected:';
		errorBox.appendChild( errorBoxTitle );

		// Error list
		const errorList = document.createElement( 'ul' );
		errorList.className = 'error-list';
		errorBox.appendChild( errorList );
		this.ui.errorList = errorList;

		// Selected container
		const selectedContainer = document.createElement( 'div' );
		selectedContainer.className = 'selected-files-container';
		this.wrap.appendChild( selectedContainer );
		this.ui.selectedContainer = selectedContainer;
	}

	_renderHints() {
		// append div with class upload-hints
		const hints = document.createElement( 'div' );
		hints.className = 'upload-hints';
		hints.innerHTML =
			`<div><strong>Allowed:</strong> ${ this.allowedExt
				.map( ( e ) => '.' + e )
				.join( ', ' ) }</div>` +
			`<span><strong>Max size:</strong> ${ this.maxSizeMB } MB per file</span>` +
			`<span><strong>Max files:</strong> ${ this.maxFiles }</span>`;

		this.ui.dropZone.appendChild( hints );
	}

	_updateCountBadge() {
		const total = this.selectedFiles.length;
		const badge = this.ui.countBadge;

		if ( total === 0 ) {
			badge.style.display = 'none';
			return;
		}

		badge.style.display = 'inline-block';
		badge.textContent = `${ total } / ${ this.maxFiles } file${
			total !== 1 ? 's' : ''
		} selected`;
		badge.classList.toggle( 'at-limit', total >= this.maxFiles );

		// Visually lock drop zone at limit
		this.ui.dropZone.classList.toggle(
			'limit-reached',
			total >= this.maxFiles
		);
	}

	_showErrors( rejections ) {
		const { errorBox, errorList } = this.ui;
		if ( rejections.length === 0 ) {
			errorBox.style.display = 'none';
			return;
		}
		errorList.innerHTML = rejections
			.map(
				( r ) =>
					`<li><strong>${ r.name }</strong>: ${ r.reasons.join(
						'; '
					) }</li>`
			)
			.join( '' );
		errorBox.style.display = 'block';
	}

	_createFileItem( data ) {
		const { id, filename, fileimage, datetime, filesize } = data;

		const box = document.createElement( 'div' );
		box.className = 'file-item';
		box.dataset.id = id;

		// Thumbnail or generic icon
		const imgDiv = document.createElement( 'div' );
		imgDiv.className = 'file-image';
		if ( this._isImage( filename ) ) {
			const img = document.createElement( 'img' );
			img.src = fileimage;
			img.alt = '';
			imgDiv.appendChild( img );
		} else {
			imgDiv.innerHTML = '<i class="far fa-file-alt"></i>';
		}

		// File metadata
		const detail = document.createElement( 'div' );
		detail.className = 'file-detail';
		detail.innerHTML = `
      <h6>${ filename }</h6>
      <p>
        <span>Size: ${ filesize }</span>
        <span style="margin-left:10px">Modified: ${ datetime }</span>
      </p>
    `;

		// Action buttons
		const actions = document.createElement( 'div' );
		actions.className = 'file-actions';

		const deleteBtn = document.createElement( 'button' );
		deleteBtn.className = 'file-action-btn';
		deleteBtn.textContent = 'Delete';
		deleteBtn.addEventListener( 'click', () => this._deleteSelected( id ) );
		actions.appendChild( deleteBtn );
		detail.appendChild( actions );
		box.appendChild( imgDiv );
		box.appendChild( detail );
		return box;
	}

	_renderSelected() {
		this.ui.selectedContainer.innerHTML = '';
		this.selectedFiles.forEach( ( f ) =>
			this.ui.selectedContainer.appendChild( this._createFileItem( f ) )
		);
		this._updateCountBadge();
	}

	// ── State mutations ───────────────────────────────────────────

	_deleteSelected( id ) {
		if (
			globalThis.confirm( 'Are you sure you want to delete this file?' )
		) {
			this.selectedFiles = this.selectedFiles.filter(
				( f ) => f.id !== id
			);
			this._renderSelected();
		}
	}

	// ── Core: process a FileList ──────────────────────────────────

	_processFiles( fileList ) {
		const rejections = [];
		const slots = this.maxFiles - this.selectedFiles.length;

		// Already at limit before even looking at new files
		if ( slots <= 0 ) {
			this._showErrors( [
				{
					name: 'Selection blocked',
					reasons: [
						`you have already reached the limit of ${ this.maxFiles } files`,
					],
				},
			] );
			return;
		}

		let accepted = 0;

		for ( const file of fileList ) {
			// Enforce count limit across this batch
			if ( accepted >= slots ) {
				rejections.push( {
					name: file.name,
					reasons: [ `max file limit of ${ this.maxFiles } reached` ],
				} );
				continue;
			}

			// Validate extension & size
			const errors = this._validateFile( file );
			if ( errors.length > 0 ) {
				rejections.push( { name: file.name, reasons: errors } );
				continue;
			}

			// All good — read as data URL
			const reader = new globalThis.FileReader();
			reader.onloadend = () => {
				this.selectedFiles.push( {
					id: this._generateId(),
					filename: file.name,
					filetype: file.type,
					fileimage: reader.result,
					datetime: file.lastModified
						? new Date( file.lastModified ).toLocaleString(
								'en-IN'
						  )
						: 'Unknown',
					filesize: this._formatSize( file.size ),
				} );
				this._renderSelected();
			};
			reader.readAsDataURL( file );
			accepted++;
		}

		this._showErrors( rejections );
	}

	// ── Utilities ─────────────────────────────────────────────────

	_generateId() {
		return (
			Math.random().toString( 36 ).slice( 2, 9 ) +
			Date.now().toString( 36 )
		);
	}

	_formatSize( bytes, decimals = 2 ) {
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

	_isImage( filename ) {
		return /\.(jpg|jpeg|png|gif|svg)$/i.test( filename );
	}

	_getExt( filename ) {
		return filename.split( '.' ).pop().toLowerCase();
	}

	// ── Validation ────────────────────────────────────────────────

	_validateFile( file ) {
		const errors = [];
		const ext = this._getExt( file.name );

		if ( ! this.allowedExt.includes( ext ) ) {
			errors.push( `".${ ext }" is not an allowed extension` );
		}

		if ( file.size > this.maxSizeBytes ) {
			errors.push(
				`exceeds max size of ${
					this.maxSizeMB
				} MB (is ${ this._formatSize( file.size ) })`
			);
		}

		return errors;
	}
}

export { FileUploader };
