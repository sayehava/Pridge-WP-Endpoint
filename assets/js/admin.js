( function () {
	'use strict';

	const root = document.querySelector( '[data-pridge-admin]' );
	if ( ! root ) {
		return;
	}

	const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( reduceMotion ) {
		root.classList.add( 'no-motion' );
	}

	const panels = Array.from( root.querySelectorAll( '.pridge-panel' ) );
	if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
		panels.forEach( ( panel ) => panel.classList.add( 'is-visible' ) );
	} else {
		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-visible' );
						observer.unobserve( entry.target );
					}
				} );
			},
			{ threshold: 0.08 }
		);
		panels.forEach( ( panel ) => observer.observe( panel ) );
	}

	let activeModal = null;
	let activeTrigger = null;

	const focusableSelector = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join( ',' );

	function openModal( modal, trigger ) {
		if ( ! modal ) {
			return;
		}

		activeModal = modal;
		activeTrigger = trigger;
		modal.hidden = false;
		document.body.classList.add( 'pridge-modal-active' );

		window.requestAnimationFrame( () => {
			modal.classList.add( 'is-open' );
			const focusable = modal.querySelector( focusableSelector );
			if ( focusable ) {
				focusable.focus();
			}
		} );
	}

	function closeModal() {
		if ( ! activeModal ) {
			return;
		}

		const modal = activeModal;
		const trigger = activeTrigger;
		modal.classList.remove( 'is-open' );
		document.body.classList.remove( 'pridge-modal-active' );
		activeModal = null;
		activeTrigger = null;

		window.setTimeout( () => {
			modal.hidden = true;
			if ( trigger ) {
				trigger.focus();
			}
		}, reduceMotion ? 0 : 180 );
	}

	root.addEventListener( 'click', ( event ) => {
		const opener = event.target.closest( '[data-pridge-modal-open]' );
		if ( opener ) {
			event.preventDefault();
			openModal( document.getElementById( opener.dataset.pridgeModalOpen ), opener );
			return;
		}

		if ( event.target.closest( '[data-pridge-modal-close]' ) ) {
			event.preventDefault();
			closeModal();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! activeModal ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			event.preventDefault();
			closeModal();
			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}

		const focusable = Array.from( activeModal.querySelectorAll( focusableSelector ) );
		if ( ! focusable.length ) {
			event.preventDefault();
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );

	root.querySelectorAll( 'form' ).forEach( ( form ) => {
		form.addEventListener( 'submit', () => {
			const submit = form.querySelector( '[type="submit"]' );
			if ( submit ) {
				submit.setAttribute( 'aria-disabled', 'true' );
				submit.classList.add( 'is-loading' );
			}
		} );
	} );

	const wooToggle = root.querySelector( '[data-pridge-woo-toggle]' );
	const germanizedToggle = root.querySelector( '[data-pridge-germanized-toggle]' );
	if ( wooToggle && germanizedToggle ) {
		const syncGermanized = () => {
			const available = '1' === germanizedToggle.dataset.pluginAvailable;
			germanizedToggle.disabled = ! available || ! wooToggle.checked;
			if ( germanizedToggle.disabled ) {
				germanizedToggle.checked = false;
			}
		};
		wooToggle.addEventListener( 'change', syncGermanized );
		syncGermanized();
	}

	const endpointForm = root.querySelector( '[data-pridge-endpoints-form]' );
	const endpointList = root.querySelector( '[data-pridge-endpoint-list]' );
	const endpointTemplate = document.getElementById( 'pridge-endpoint-template' );
	const addEndpoint = root.querySelector( '[data-pridge-add-endpoint]' );

	function endpointId() {
		if ( window.crypto && 'randomUUID' in window.crypto ) {
			return `endpoint-${ window.crypto.randomUUID() }`;
		}

		return `endpoint-${ Date.now() }-${ Math.floor( Math.random() * 100000 ) }`;
	}

	function addEndpointOptions( id, label ) {
		root.querySelectorAll( '[data-endpoint-select]' ).forEach( ( select ) => {
			if ( ! select.querySelector( `option[value="${ id }"]` ) ) {
				const option = document.createElement( 'option' );
				option.value = id;
				option.textContent = label;
				select.appendChild( option );
			}
		} );
	}

	function updateEndpointOptions( id, label ) {
		root.querySelectorAll( '[data-endpoint-select]' ).forEach( ( select ) => {
			const option = Array.from( select.options ).find( ( item ) => item.value === id );
			if ( option ) {
				option.textContent = label || id;
			}
		} );
	}

	if ( endpointForm && endpointList && endpointTemplate && addEndpoint ) {
		addEndpoint.addEventListener( 'click', () => {
			const id = endpointId();
			const index = `${ Date.now() }-${ endpointList.children.length }`;
			const html = endpointTemplate.innerHTML.replaceAll( '__ID__', id ).replaceAll( '__INDEX__', index );
			endpointList.insertAdjacentHTML( 'beforeend', html );
			const row = endpointList.lastElementChild;
			addEndpointOptions( id, id );
			row.querySelector( '[data-endpoint-name]' ).focus();
		} );

		endpointList.addEventListener( 'input', ( event ) => {
			if ( event.target.matches( '[data-endpoint-name]' ) ) {
				const row = event.target.closest( '[data-endpoint-id]' );
				updateEndpointOptions( row.dataset.endpointId, event.target.value );
			}
		} );

		endpointList.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest( '[data-pridge-remove-endpoint]' );
			if ( ! remove ) {
				return;
			}

			const row = remove.closest( '[data-endpoint-id]' );
			const id = row.dataset.endpointId;
			root.querySelectorAll( '[data-endpoint-select]' ).forEach( ( select ) => {
				Array.from( select.options ).filter( ( option ) => option.value === id ).forEach( ( option ) => option.remove() );
			} );
			row.remove();
		} );
	}

	const cronMonitor = root.querySelector( '[data-pridge-cron-monitor]' );
	const cronConfig = window.PridgeAdmin || {};
	if ( cronMonitor && cronConfig.ajaxUrl && cronConfig.cronStatusNonce ) {
		const healthCard = cronMonitor.querySelector( '[data-cron-health-card]' );
		const healthText = cronMonitor.querySelector( '[data-cron-health]' );
		const liveDot     = cronMonitor.querySelector( '[data-cron-live-dot]' );
		const lastRunEl   = cronMonitor.querySelector( '[data-cron-last-run]' );
		const nextRunEl   = cronMonitor.querySelector( '[data-cron-next-run]' );
		const pendingEl   = cronMonitor.querySelector( '[data-cron-pending]' );
		const warningEl   = cronMonitor.querySelector( '[data-cron-warning]' );
		const cronLabels  = cronConfig.cronLabels || {};

		let lastKnownRun = parseInt( cronMonitor.dataset.lastRun || '0', 10 );

		async function pollCronStatus() {
			const form = new FormData();
			form.append( 'action', 'pridge_wp_cron_status' );
			form.append( 'nonce', cronConfig.cronStatusNonce );

			try {
				const response = await fetch( cronConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form } );
				const body = await response.json();
				if ( ! response.ok || ! body.success ) {
					return;
				}

				const data = body.data;

				if ( healthText ) {
					healthText.textContent = data.healthy ? cronLabels.running : cronLabels.notConfirmed;
				}
				if ( healthCard ) {
					healthCard.classList.toggle( 'is-healthy', data.healthy );
					healthCard.classList.toggle( 'is-unhealthy', ! data.healthy );
				}
				if ( liveDot ) {
					liveDot.classList.toggle( 'is-healthy', data.healthy );
				}
				if ( lastRunEl ) {
					lastRunEl.textContent = data.lastRunRelative;
				}
				if ( nextRunEl ) {
					nextRunEl.textContent = data.nextRunRelative;
				}
				if ( pendingEl ) {
					pendingEl.textContent = data.pendingCount;
				}
				if ( warningEl ) {
					warningEl.hidden = data.healthy;
				}

				if ( data.lastRun && data.lastRun !== lastKnownRun ) {
					lastKnownRun = data.lastRun;
					if ( healthCard && ! reduceMotion ) {
						healthCard.classList.add( 'is-flash' );
						window.setTimeout( () => healthCard.classList.remove( 'is-flash' ), 1200 );
					}
				}
			} catch ( error ) {
				// A missed poll is not worth surfacing; the next interval tries again.
			}
		}

		window.setInterval( pollCronStatus, 20000 );
	}

	const archiveModal = document.getElementById( 'pridge-archive-modal' );
	const archiveSummary = root.querySelector( '[data-pridge-archive-summary]' );
	const archivePreview = root.querySelector( '[data-pridge-archive-preview]' );
	const archiveMetadata = root.querySelector( '[data-pridge-archive-metadata]' );
	const archiveConfig = window.PridgeAdmin || {};
	const archiveLabels = archiveConfig.archiveLabels || {};

	function summaryItem( label, value ) {
		const item = document.createElement( 'div' );
		const name = document.createElement( 'span' );
		const content = document.createElement( 'strong' );
		name.textContent = label;
		content.textContent = value || '—';
		item.append( name, content );
		return item;
	}

	function renderArchiveDetail( detail ) {
		archiveSummary.replaceChildren(
			summaryItem( archiveLabels.document, detail.documentType ),
			summaryItem( archiveLabels.endpoint, detail.endpoint ),
			summaryItem( archiveLabels.status, detail.status ),
			summaryItem( archiveLabels.created, detail.createdAt ),
			summaryItem( archiveLabels.order, detail.orderId ? `#${ detail.orderId }` : '—' ),
			summaryItem( archiveLabels.size, `${ detail.byteCount } ${ archiveLabels.bytes }` ),
			summaryItem( archiveLabels.source, detail.source ),
			summaryItem( archiveLabels.job, detail.jobId ? `#${ detail.jobId }` : '—' ),
			summaryItem( archiveLabels.error, detail.errorCode )
		);
		archivePreview.replaceChildren();

		if ( 'document' === detail.previewKind && detail.payloadUrl ) {
			const frame = document.createElement( 'iframe' );
			frame.title = detail.documentType;
			frame.src = detail.payloadUrl;
			archivePreview.appendChild( frame );
		} else {
			const preview = document.createElement( 'pre' );
			preview.textContent = detail.preview || archiveConfig.emptyText;
			archivePreview.appendChild( preview );
		}

		archiveMetadata.textContent = detail.metadata || '{}';
	}

	root.addEventListener( 'click', async ( event ) => {
		const trigger = event.target.closest( '[data-pridge-archive-view]' );
		if ( ! trigger || ! archiveModal || ! archiveSummary || ! archivePreview || ! archiveMetadata ) {
			return;
		}

		event.preventDefault();
		trigger.disabled = true;
		archiveSummary.replaceChildren();
		archiveMetadata.textContent = '';
		archivePreview.textContent = archiveConfig.loadingText || '';
		openModal( archiveModal, trigger );

		const form = new FormData();
		form.append( 'action', 'pridge_wp_archive_detail' );
		form.append( 'nonce', archiveConfig.archiveNonce || '' );
		form.append( 'archive_id', trigger.dataset.pridgeArchiveView );

		try {
			const response = await fetch( archiveConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form } );
			const body = await response.json();
			if ( ! response.ok || ! body.success ) {
				throw new Error( body.data && body.data.message ? body.data.message : archiveConfig.errorText );
			}
			renderArchiveDetail( body.data );
		} catch ( error ) {
			archivePreview.textContent = error.message || archiveConfig.errorText;
		} finally {
			trigger.disabled = false;
		}
	} );
}() );
