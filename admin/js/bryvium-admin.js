( function () {
	'use strict';

	const config = window.BryviumAdminConfig;
	const rows = document.getElementById( 'bwfc-knowledge-entries' );
	if ( ! config || ! rows ) {
		return;
	}

	const elements = {
		notice: document.getElementById( 'bwfc-knowledge-notice' ),
		empty: document.getElementById( 'bwfc-knowledge-empty' ),
		filters: document.getElementById( 'bwfc-knowledge-filters' ),
		search: document.getElementById( 'bwfc-knowledge-search' ),
		category: document.getElementById( 'bwfc-knowledge-category' ),
		previous: document.getElementById( 'bwfc-knowledge-previous' ),
		next: document.getElementById( 'bwfc-knowledge-next' ),
		page: document.getElementById( 'bwfc-knowledge-page' ),
		form: document.getElementById( 'bwfc-knowledge-form' ),
		formTitle: document.getElementById( 'bwfc-knowledge-form-title' ),
		id: document.getElementById( 'bwfc-knowledge-id' ),
		title: document.getElementById( 'bwfc-knowledge-title' ),
		answer: document.getElementById( 'bwfc-knowledge-answer' ),
		keywords: document.getElementById( 'bwfc-knowledge-keywords' ),
		categoryInput: document.getElementById( 'bwfc-knowledge-category-input' ),
		enabled: document.getElementById( 'bwfc-knowledge-enabled' ),
		submit: document.getElementById( 'bwfc-knowledge-submit' ),
		cancel: document.getElementById( 'bwfc-knowledge-cancel' ),
	};
	const formNotice = document.createElement( 'div' );
	formNotice.className = 'notice';
	formNotice.setAttribute( 'role', 'alert' );
	formNotice.hidden = true;
	elements.form.insertBefore( formNotice, elements.form.firstChild );
	const fields = {
		title: elements.title,
		answer: elements.answer,
		keywords: elements.keywords,
		category: elements.categoryInput,
		enabled: elements.enabled,
	};
	const pageSize = 10;
	let currentPage = 1;
	let totalPages = 1;
	let isSaving = false;

	function showNotice( message, type ) {
		elements.notice.textContent = message;
		elements.notice.className = 'notice notice-' + type;
		elements.notice.hidden = false;
	}

	function clearNotice() {
		elements.notice.textContent = '';
		elements.notice.className = 'notice';
		elements.notice.hidden = true;
	}

	function clearFieldErrors() {
		formNotice.textContent = '';
		formNotice.className = 'notice';
		formNotice.hidden = true;
		Object.values( fields ).forEach( function ( field ) {
			field.removeAttribute( 'aria-invalid' );
			const error = field.parentElement.querySelector( '.bwfc-field-error' );
			if ( error ) {
				error.remove();
			}
		} );
	}

	function showFormError( error ) {
		clearFieldErrors();
		formNotice.textContent = error.message || config.strings.saveError;
		formNotice.className = 'notice notice-error';
		formNotice.hidden = false;

		const params = error.data && error.data.params ? error.data.params : {};
		const invalidFields = error.data && Array.isArray( error.data.fields )
			? error.data.fields
			: Object.keys( params );
		invalidFields.forEach( function ( key ) {
			const field = fields[ key ];
			if ( ! field ) {
				return;
			}
			const message = params[ key ] || error.message;
			const fieldError = document.createElement( 'span' );
			fieldError.className = 'bwfc-field-error';
			fieldError.setAttribute( 'role', 'alert' );
			fieldError.textContent = message;
			field.setAttribute( 'aria-invalid', 'true' );
			field.insertAdjacentElement( 'afterend', fieldError );
		} );
	}

	function addCell( row, value ) {
		const cell = document.createElement( 'td' );
		cell.textContent = value || '—';
		row.appendChild( cell );
		return cell;
	}

	function resetForm() {
		clearFieldErrors();
		elements.form.reset();
		elements.id.value = '';
		elements.enabled.checked = true;
		elements.formTitle.textContent = config.strings.addTitle;
		elements.submit.textContent = config.strings.addButton;
		elements.cancel.hidden = true;
	}

	function editEntry( entry ) {
		elements.id.value = entry.id || '';
		elements.title.value = entry.title || '';
		elements.answer.value = entry.answer || '';
		elements.keywords.value = Array.isArray( entry.keywords ) ? entry.keywords.join( ', ' ) : '';
		elements.categoryInput.value = entry.category || '';
		elements.enabled.checked = entry.enabled !== false;
		elements.formTitle.textContent = config.strings.editTitle;
		elements.submit.textContent = config.strings.saveButton;
		elements.cancel.hidden = false;
		elements.title.focus();
	}

	function renderEntries( entries ) {
		rows.replaceChildren();
		elements.empty.hidden = entries.length > 0;

		entries.forEach( function ( entry ) {
			const row = document.createElement( 'tr' );
			addCell( row, entry.title );
			addCell( row, entry.category );
			addCell( row, Array.isArray( entry.keywords ) ? entry.keywords.join( ', ' ) : '' );
			addCell( row, entry.enabled === false ? 'Disabled' : 'Enabled' );

			const actions = document.createElement( 'td' );
			const actionGroup = document.createElement( 'div' );
			actionGroup.className = 'bwfc-knowledge-actions';
			const editButton = document.createElement( 'button' );
			editButton.className = 'button button-small';
			editButton.type = 'button';
			editButton.textContent = config.strings.editButton;
			editButton.addEventListener( 'click', function () {
				editEntry( entry );
			} );
			const deleteButton = document.createElement( 'button' );
			deleteButton.className = 'button button-small';
			deleteButton.type = 'button';
			deleteButton.textContent = config.strings.deleteButton;
			deleteButton.addEventListener( 'click', function () {
				deleteEntry( entry );
			} );
			actionGroup.append( editButton, deleteButton );
			actions.appendChild( actionGroup );
			row.appendChild( actions );
			rows.appendChild( row );
		} );
	}

	function renderPagination() {
		elements.page.textContent = config.strings.page
			.replace( '%1$d', String( currentPage ) )
			.replace( '%2$d', String( totalPages ) );
		elements.previous.disabled = currentPage <= 1;
		elements.next.disabled = currentPage >= totalPages;
	}

	async function request( url, options ) {
		const response = await fetch( url, Object.assign( {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json',
			},
		}, options || {} ) );
		const body = await response.json().catch( function () {
			return {};
		} );
		if ( ! response.ok ) {
			const error = new Error( body.message || config.strings.saveError );
			error.status = response.status;
			error.data = body.data || {};
			throw error;
		}
		return { body: body, response: response };
	}

	async function loadEntries() {
		clearNotice();
		elements.page.textContent = config.strings.loading;
		elements.previous.disabled = true;
		elements.next.disabled = true;
		const params = new URLSearchParams( {
			search: elements.search.value.trim(),
			category: elements.category.value,
			page: String( currentPage ),
			per_page: String( pageSize ),
		} );
		try {
			const result = await request( config.restUrl + '?' + params.toString() );
			const body = result.body;
			const entries = Array.isArray( body ) ? body : ( Array.isArray( body.entries ) ? body.entries : [] );
			const headerPages = parseInt( result.response.headers.get( 'X-WP-TotalPages' ), 10 );
			totalPages = Number.isFinite( headerPages ) && headerPages > 0
				? headerPages
				: ( Number.isInteger( body.total_pages ) && body.total_pages > 0
					? body.total_pages
					: ( Number.isInteger( body.pages ) && body.pages > 0 ? body.pages : 1 ) );
			if ( currentPage > totalPages ) {
				currentPage = totalPages;
				return loadEntries();
			}
			renderEntries( entries );
			renderPagination();
			return true;
		} catch ( error ) {
			renderEntries( [] );
			elements.empty.hidden = true;
			totalPages = 1;
			renderPagination();
			showNotice( error.message || config.strings.loadError, 'error' );
			return false;
		}
	}

	function makePayload() {
		return {
			title: elements.title.value.trim(),
			answer: elements.answer.value.trim(),
			keywords: elements.keywords.value.split( ',' ).map( function ( keyword ) {
				return keyword.trim();
			} ).filter( Boolean ),
			category: elements.categoryInput.value.trim(),
			enabled: elements.enabled.checked,
		};
	}

	async function saveEntry( event ) {
		event.preventDefault();
		clearFieldErrors();
		clearNotice();
		if ( isSaving ) {
			return;
		}
		if ( ! elements.form.reportValidity() ) {
			return;
		}

		const payload = makePayload();
		if ( ! payload.title || ! payload.answer ) {
			const missing = payload.title ? 'answer' : 'title';
			const error = new Error( config.strings.requiredFields );
			error.data = { fields: [ missing ] };
			showFormError( error );
			( payload.title ? elements.answer : elements.title ).focus();
			return;
		}
		if ( payload.keywords.length > 20 ) {
			const error = new Error( config.strings.keywordLimit );
			error.data = { fields: [ 'keywords' ] };
			showFormError( error );
			elements.keywords.focus();
			return;
		}

		const editing = Boolean( elements.id.value );
		const url = editing ? config.restUrl + '/' + encodeURIComponent( elements.id.value ) : config.restUrl;
		isSaving = true;
		elements.submit.disabled = true;
		try {
			await request( url, {
				method: editing ? 'PUT' : 'POST',
				body: JSON.stringify( payload ),
			} );
			resetForm();
			currentPage = 1;
			const loaded = await loadEntries();
			if ( loaded ) {
				showNotice( editing ? config.strings.updated : config.strings.added, 'success' );
			}
		} catch ( error ) {
			showFormError( error );
		} finally {
			isSaving = false;
			elements.submit.disabled = false;
		}
	}

	async function deleteEntry( entry ) {
		if ( ! window.confirm( config.strings.deleteConfirm.replace( '%s', entry.title || config.strings.deleteFallback ) ) ) {
			return;
		}

		const deleteButton = document.activeElement;
		if ( deleteButton ) {
			deleteButton.disabled = true;
		}
		try {
			await request( config.restUrl + '/' + encodeURIComponent( entry.id ), { method: 'DELETE' } );
			if ( elements.id.value === entry.id ) {
				resetForm();
			}
			const loaded = await loadEntries();
			if ( loaded ) {
				showNotice( config.strings.deleted, 'success' );
			}
		} catch ( error ) {
			showNotice( error.message || config.strings.deleteError, 'error' );
		} finally {
			if ( deleteButton ) {
				deleteButton.disabled = false;
			}
		}
	}

	elements.filters.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		currentPage = 1;
		loadEntries();
	} );
	elements.category.addEventListener( 'change', function () {
		currentPage = 1;
		loadEntries();
	} );
	elements.previous.addEventListener( 'click', function () {
		if ( currentPage > 1 ) {
			currentPage -= 1;
			loadEntries();
		}
	} );
	elements.next.addEventListener( 'click', function () {
		if ( currentPage < totalPages ) {
			currentPage += 1;
			loadEntries();
		}
	} );
	elements.form.addEventListener( 'submit', saveEntry );
	elements.cancel.addEventListener( 'click', resetForm );
	loadEntries();
}() );
