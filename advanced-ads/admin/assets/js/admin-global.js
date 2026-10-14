/* eslint-disable no-unused-vars */
/* eslint-disable camelcase */
/*
 * global js functions for Advanced Ads
 */
jQuery(document).ready(function () {
	/**
	 * ADMIN NOTICES
	 */
	// close button
	// .advads-notice-dismiss class can be used to add a custom close button (e.g., link)
	jQuery(document).on(
		'click',
		'.advads-admin-notice .notice-dismiss, .advads-notice-dismiss',
		function (event) {
			event.preventDefault();
			const messagebox = jQuery(this).parents('.advads-admin-notice');
			if (messagebox.attr('data-notice') === undefined) return;

			const query = {
				action: 'advads-close-notice',
				notice: messagebox.attr('data-notice'),
				nonce: advadsglobal.ajax_nonce,
			};
			// send query
			jQuery.post(ajaxurl, query, function (r) {
				messagebox.fadeOut();
			});
		}
	);
	// hide notice for 7 days
	jQuery(document).on(
		'click',
		'.advads-admin-notice .advads-notice-hide',
		function () {
			const messagebox = jQuery(this).parents('.advads-admin-notice');
			if (messagebox.attr('data-notice') === undefined) return;

			const query = {
				action: 'advads-hide-notice',
				notice: messagebox.attr('data-notice'),
				nonce: advadsglobal.ajax_nonce,
			};
			// send query
			jQuery.post(ajaxurl, query, function (r) {
				messagebox.fadeOut();
			});
		}
	);

	// autoresponder button
	jQuery('body').on('click', '.advads-notices-button-subscribe', function () {
		if (this.dataset.notice === undefined) {
			return;
		}
		const messageboxes = jQuery(this).parents('.advads-admin-notice');
		if (!messageboxes.length) {
			return;
		}
		const $messagebox = jQuery(messageboxes[0]);
		jQuery('<span class="spinner advads-spinner"></span>').insertAfter(
			this
		);

		const query = {
			action: 'advads-subscribe-notice',
			notice: this.dataset.notice,
			nonce: advadsglobal.ajax_nonce,
		};
		// send and replace with server message
		jQuery
			.post(ajaxurl, query)
			.success(function (response) {
				$messagebox
					.children('.advads-notice-box_wrapper')
					.html('<p>' + response.data.message + '</p>');
				$messagebox.addClass('notice-success notice');
			})
			.fail(function (response) {
				$messagebox
					.children('.advads-notice-box_wrapper')
					.html('<p>' + response.responseJSON.data.message + '</p>');
				$messagebox.addClass('notice-error notice');
			})
			.always(function () {
				$messagebox.removeClass('notice-info');
			});
	});

	/**
	 * Functions for Ad Health Notifications in the backend
	 */
	// hide button (adds item to "ignore" list)
	jQuery(document).on('click', '.advads-ad-health-notice-hide', function () {
		const notice = jQuery(this).parents('li');
		if (notice.attr('data-notice') === undefined) return;
		// var list = notice.parent( 'ul' );
		const remove = jQuery(this).hasClass('remove');

		// fix height to prevent the box from going smaller first, then show the "show" link and grow again
		const notice_box = jQuery('#advads_overview_notices');
		notice_box.css('height', notice_box.height() + 'px');

		const query = {
			action: 'advads-ad-health-notice-hide',
			notice: notice.attr('data-notice'),
			nonce: advadsglobal.ajax_nonce,
		};
		// fade out first or remove, so users can’t click twice
		if (remove) {
			notice.remove();
		} else {
			notice.hide();
		}
		// show loader
		notice_box.find('.advads-loader').show();
		advads_ad_health_maybe_remove_list();
		// send query
		jQuery.post(ajaxurl, query, function (r) {
			// update number in menu
			advads_ad_health_reload_number_in_menu();
			// update show button
			advads_ad_health_reload_show_link();
			// If no visible items remain, hide the "Dismiss notices" button wrapper
			if (jQuery('#advads_overview_notices .advads-ad-health-notices > li:visible').length === 0) {
                jQuery('.advads-ad-health-notice-hide-all').closest('.advads-widget-buttons').hide();
            }
			// remove the fixed height
			jQuery('#advads_overview_notices').css('height', '');
			// remove loader
			notice_box.find('.advads-loader').hide();
		});
	});

	// dismiss all notices button
	jQuery(document).on('click', '.advads-ad-health-notice-hide-all', function () {
		const $button = jQuery(this);
		const notice_box = jQuery('#advads_overview_notices');

		// disable the button immediately to prevent double-clicks
		$button.prop('disabled', true);

		// show loader
		notice_box.find('.advads-loader').show();

		const query = {
			action: 'advads-ad-health-notice-hide-all',
			nonce: advadsglobal.ajax_nonce,
		};

		jQuery.post(ajaxurl, query, function () {
			// Evaluate and hide/remove each notice individually based on its properties
			notice_box.find('.advads-ad-health-notices > li').each(function () {
				const $li = jQuery(this);
				const $hideBtn = $li.find('.advads-ad-health-notice-hide');

				// If there's no dismiss button, leave it visible
				if ($hideBtn.length === 0) {
					return;
				}

				// If it's a permanent removal notice, remove it from the DOM. Otherwise, hide it.
				if ($hideBtn.hasClass('remove')) {
					$li.remove();
				} else {
					$li.hide();
				}
			});

			// hide list fragments if last item was hidden/removed
			advads_ad_health_maybe_remove_list();
			// update the sidebar menu counter
			advads_ad_health_reload_number_in_menu();
			// update the "show hidden" link
			advads_ad_health_reload_show_link();
			// hide the button itself since dismissible items are processed
			$button.closest('.advads-widget-buttons').hide();
			// hide loader
			notice_box.find('.advads-loader').hide();
		});
	});

	// show all hidden notices
	jQuery(document).on(
		'click',
		'.advads-ad-health-notices-show-hidden',
		function () {
			advads_ad_health_show_hidden();
		}
	);

	// Keyboard users open the help with Tab and close it with Escape.
	jQuery('.advads-help')
		.attr({ tabindex: 0, role: 'note' })
		.on('keydown', function (event) {
			if (event.key === 'Escape') {
				this.blur();
			}
		});

	jQuery('.advads-help').on('mouseenter focus', function (event) {
		const tooltip = jQuery(event.target).children('.advads-tooltip')[0];
		if (typeof tooltip === 'undefined') {
			return;
		}

		// reset inline styles before getting bounding client rect.
		tooltip.style.position = '';
		tooltip.style.left = '';
		tooltip.style.top = '';

		const topParentRect = document
				.getElementById('wpbody')
				.getBoundingClientRect(),
			helpRect = event.target.getBoundingClientRect(),
			offsets = {
				left: Math.ceil(helpRect.left) + 13,
				top: Math.ceil(helpRect.top) + 13,
			};
		let tooltipRect = tooltip.getBoundingClientRect();

		tooltip.style.position = 'fixed';
		tooltip.style.left = offsets.left + 'px';
		tooltip.style.top = offsets.top + 'px';

		// check element is not overflowing to the right.
		while (tooltipRect.right > topParentRect.right - 20) {
			offsets.left -= 10;
			tooltip.style.left = offsets.left + 'px';
			tooltipRect = tooltip.getBoundingClientRect();
		}

		// check element is not overflowing bottom of parent and is within viewport.
		while (tooltipRect.bottom > Math.min(topParentRect.bottom, jQuery(window).height()) - 20) {
			offsets.top -= 10;
			tooltip.style.top = offsets.top + 'px';
			tooltipRect = tooltip.getBoundingClientRect();
		}
	});
});

// remove duplicate close buttons
jQuery(window).on('load', function () {
	jQuery('a.notice-dismiss').next('button.notice-dismiss').remove();
});

/**
 * Ad Health Notices in backend
 */
// display notices list (deprecated because we load it without AJAX now)
function advads_display_ad_health_notices() {
	const query = {
		action: 'advads-ad-health-notice-display',
		nonce: advadsglobal.ajax_nonce,
	};

	const widget = jQuery('#advads_overview_notices .main');

	// add loader icon to the widget
	widget.html('<span class="advads-loader"></span>');
	// send query
	jQuery.post(ajaxurl, query, function (r) {
		widget.html(r);

		// update number in menu
		advads_ad_health_reload_number_in_menu();
		// update list headlines
		advads_ad_health_maybe_remove_list();

		// remove widget, if return is empty
		if (r === '') {
			jQuery('#advads_overview_notices').remove();
		}
	});
}
// push a notice to the queue
function advads_push_notice(key, attr = '') {
	const query = {
		action: 'advads-ad-health-notice-push-adminui',
		key,
		attr,
		nonce: advadsglobal.ajax_nonce,
	};
	// send query
	jQuery.post(ajaxurl, query, function (r) {});
}
// show notices of a given type again
function advads_ad_health_show_hidden() {
	const notice_box = jQuery('#advads__overview_notices');
	const query = {
		action: 'advads-ad-health-notice-unignore',
		nonce: advadsglobal.ajax_nonce,
	};
	// show all hidden
	jQuery(document)
		.find('#advads_overview_notices .advads-ad-health-notices > li:hidden')
		.show();
	// show loader
	notice_box.find('.advads-loader').show();
	// show the "Dismiss all" button again
    jQuery('.advads-ad-health-notice-hide-all')
		.prop('disabled', false)
		.closest('.advads-widget-buttons')
		.show();
	// update the button
	advads_ad_health_reload_show_link();
	advads_ad_health_maybe_remove_list();
	// send query
	jQuery.post(ajaxurl, query, function (r) {
		// update issue count
		advads_ad_health_reload_number_in_menu();
		// hide loader
		notice_box.find('.advads-loader').hide();
	});
}
// hide list fragments if last item was hidden/removed
function advads_ad_health_maybe_remove_list() {
	// get all lists
	const lists = jQuery(document).find(
		'#advads_overview_notices .advads-ad-health-notices'
	);

	// check each list separately
	lists.each(function (index) {
		const list = jQuery(this);
		// check if there are visible items in the list
		if (list.find('li:visible').length) {
			// show parent headline
			list.prev('h3').show();
		} else {
			// hide parent headline
			list.prev('h3').hide();
		}
	});
}
// reload number of notices shown in the sidebar based on element in the problems list
function advads_ad_health_reload_number_in_menu() {
	// get number of notices
	const number = jQuery(document).find(
		'#advads_overview_notices .advads-ad-health-notices > li:visible'
	).length;
	jQuery('#toplevel_page_advanced-ads .update-count').html(number);
}
// update show X issues link – number and visibility
function advads_ad_health_reload_show_link() {
	// get number of invisible elements
	const number = jQuery(document).find(
		'#advads_overview_notices .advads-ad-health-notices > li:hidden'
	).length;
	const show_link = jQuery('.advads-ad-health-notices-show-hidden');
	// update number in the link
	jQuery('.advads-ad-health-notices-show-hidden .count').html(number);
	// hide of show, depending on number
	if (0 === number) {
		show_link.hide();
	} else {
		show_link.show();
	}
}

//Radio Toggle visibility
function toggle_visibility(currentElement, toggleElement) {
	jQuery(toggleElement).toggle(jQuery(currentElement).val() === 'on');
}
