( function () {
	'use strict';

	const forms = Array.from( document.querySelectorAll( '.uplink-mbe-settings-form' ) );
	const messages = window.uplinkMbeSettings || {};
	const floatingSave = document.querySelector( '[data-uplink-mbe-floating-save]' );
	const saveState = document.querySelector( '[data-uplink-mbe-save-state]' );
	const resetForm = document.querySelector( '[data-uplink-mbe-reset-form]' );
	const managerSettings = document.querySelector( '[data-uplink-mbe-manager-settings]' );
	const wordpressSettings = document.querySelector( '[data-uplink-mbe-wordpress-settings]' );
	const managerChoices = Array.from( document.querySelectorAll( 'input[name="uplink_mbe_settings[manager_experience]"]' ) );
	let submitting = false;

	function updateManagerSettings() {
		if ( ! managerSettings && ! wordpressSettings ) {
			return;
		}
		const selected = managerChoices.find( ( choice ) => choice.checked );
		if ( managerSettings ) {
			managerSettings.hidden = selected?.value !== 'manager_default';
		}
		if ( wordpressSettings ) {
			wordpressSettings.hidden = selected?.value !== 'wordpress';
		}
	}

	function formState( form ) {
		return new URLSearchParams( new FormData( form ) ).toString();
	}

	function isDirty( form ) {
		return formState( form ) !== form.dataset.uplinkMbeInitialState;
	}

	function updateState() {
		const dirty = forms.some( isDirty );
		if ( floatingSave ) {
			floatingSave.classList.toggle( 'is-dirty', dirty );
		}
		if ( saveState ) {
			saveState.textContent = dirty ? ( messages.unsaved || 'Unsaved changes' ) : '';
		}
		return dirty;
	}

	forms.forEach( ( form ) => {
		form.dataset.uplinkMbeInitialState = formState( form );
		form.addEventListener( 'input', updateState );
		form.addEventListener( 'change', updateState );
		form.addEventListener( 'submit', () => {
			submitting = true;
		} );
	} );

	managerChoices.forEach( ( choice ) => choice.addEventListener( 'change', updateManagerSettings ) );
	updateManagerSettings();

	if ( resetForm ) {
		resetForm.addEventListener( 'submit', ( event ) => {
			if ( ! window.confirm( messages.resetConfirm || 'Reset all Media Bridge settings to their defaults?' ) ) {
				event.preventDefault();
				return;
			}
			submitting = true;
		} );
	}

	window.addEventListener( 'beforeunload', ( event ) => {
		if ( submitting || ! updateState() ) {
			return;
		}
		event.preventDefault();
		event.returnValue = '';
	} );
}() );
