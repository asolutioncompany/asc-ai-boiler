(function ($) {
	'use strict';

	function initSettingsMediaPicker() {
		$('.example-settings-media-select').on('click', function (e) {
			e.preventDefault();
			var $button = $(this);
			var targetInputId = $button.data('target-input');
			var $input = $('#' + targetInputId);
			var $row = $button.closest('td');

			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var frame = wp.media({
				title: 'Select default image',
				button: { text: 'Select Image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var attachmentId = attachment.id;
				var imageUrl = attachment.url;
				if (attachment.sizes && attachment.sizes.medium) {
					imageUrl = attachment.sizes.medium.url;
				}

				$input.val(attachmentId);
				$row.find('.example-settings-media-id-text').text(attachmentId);
				var $preview = $row.find('.example-settings-media-preview');
				$preview.attr('src', imageUrl).show();
			});

			frame.open();
		});

		$('.example-settings-media-clear').on('click', function (e) {
			e.preventDefault();
			var $button = $(this);
			var targetInputId = $button.data('target-input');
			var $input = $('#' + targetInputId);
			var $row = $button.closest('td');

			$input.val(0);
			$row.find('.example-settings-media-id-text').text('0');
			$row.find('.example-settings-media-preview').attr('src', '').hide();
		});
	}

	$(document).ready(function () {
		initSettingsMediaPicker();
	});
})(jQuery);
