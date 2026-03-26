import jQuery from 'jquery';
import apiFetch from '@wordpress/api-fetch';

/**
 * Disable inputs on a form
 *
 * @param {Node}    form     the form.
 * @param {boolean} disabled disable inputs if `true`.
 */
function disable(form, disabled) {
	if ('undefined' === typeof disabled) {
		disabled = true;
	}

	if (!form.useableInputs) {
		form.useableInputs = jQuery(form)
			.closest('dialog')
			.find('select,input,textarea,button,a.button')
			.not(':disabled');
	}

	form.useableInputs.prop('disabled', disabled);
}

/**
 * Edit group form
 *
 * @param {Node} form the form node.
 */
function submitUpdateGroup(form) {
	const $form = jQuery(form),
		formData = $form.serialize();

	disable(form);
	apiFetch({
		path: '/advanced-ads/v1/group',
		method: 'PUT',
		data: {
			fields: formData,
		},
	}).then(function (response) {
		if (response.error) {
			// Show an error message if there is an "error" field in the response
			disable(form, false);
			form.closest('dialog').close();
			window.advancedAds.notifications.addError(response.error);
			return;
		}

		const dialog = form.closest('dialog');
		dialog.advadsTermination.resetInitialValues();

		if (response.reload) {
			// Reload the page if needed.
			localStorage.setItem(
				'advadsUpdateMessage',
				JSON.stringify({
					type: 'success',
					message: window.advancedAds.i18n.groups.updated,
				})
			);
			window.location.reload();
			return;
		}

		window.advancedAds.notifications.addSuccess(
			window.advancedAds.i18n.groups.updated
		);

		dialog.close();
	});
}

/**
 * Create new group
 *
 * @param {Node} form the form.
 */
function submitNewGroup(form) {
	const $form = jQuery(form),
		formData = $form.serialize();
	disable(form);
	apiFetch({
		path: '/advanced-ads/v1/group',
		method: 'POST',
		data: {
			fields: formData,
		},
	}).then(function (response) {
		if (response.error) {
			// Show an error message if there is an "error" field in the response
			disable(form, false);
			form.closest('dialog').close();
			window.advancedAds.notifications.addError(response.error);
			return;
		}

		const dialog = form.closest('dialog');
		dialog.advadsTermination.resetInitialValues();
		document.location.href = `#modal-group-edit-${response.group_data.id}`;
		localStorage.setItem(
			'advadsUpdateMessage',
			JSON.stringify({
				type: 'success',
				message: window.advancedAds.i18n.groups.saveNew,
			})
		);
		document.location.reload();
	});
}

export default function () {
	// Stop create group form submission.
	wp.hooks.addFilter(
		'advanced-ads-submit-modal-form',
		'advancedAds',
		function (send, form) {
			if ('advads-group-new-form' === form.id) {
				submitNewGroup(form);
				return false;
			}
			return send;
		}
	);

	// Stop edit group form submission.
	wp.hooks.addFilter(
		'advanced-ads-submit-modal-form',
		'advancedAds',
		function (send, form) {
			if ('update-group' === form.name) {
				submitUpdateGroup(form);
				return false;
			}
			return send;
		}
	);

	// Add custom submit button for each edit group form.
	jQuery('[id^="modal-group-edit-"]').each(function () {
		jQuery(this)
			.find('.advads-modal-footer')
			.html(
				`<button class="button button-primary submit-edit-group">${window.advancedAds.i18n.groups.save}</button>`
			);
	});

	// Add custom submit button for the create group form.
	jQuery('#modal-group-new')
		.find('.advads-modal-footer')
		.html(
			`<button class="button button-primary" id="submit-new-group">${window.advancedAds.i18n.groups.saveNew}</button>`
		);

	// Click on custom submit button of an edit group form.
	jQuery(document).on('click', '.submit-edit-group', function () {
		submitUpdateGroup(jQuery(this).closest('dialog').find('form')[0]);
	});

	// Click on the submit button for the create group form.
	jQuery(document).on('click', '#submit-new-group', function () {
		const $form = jQuery('#advads-group-new-form'),
			validation = $form.closest('dialog')[0].closeValidation;
		if (!window[validation.function](validation.modal_id)) {
			return;
		}
		submitNewGroup($form[0]);
	});
}
