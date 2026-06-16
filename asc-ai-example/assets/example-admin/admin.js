/*!
 * Example Admin Javascript
 */

(function ($) {
	'use strict';

	function syncProjectGalleryInput($wrap) {
		var ids = [];
		$wrap.find('.example-project-gallery-admin__item').each(function () {
			var id = parseInt($(this).attr('data-id'), 10);
			if (id > 0) {
				ids.push(id);
			}
		});
		$wrap.find('.example-project-gallery-admin__ids').val(ids.join(','));
	}

	function initProjectGalleryAdmin() {
		$('.example-project-gallery-admin').each(function () {
			var $wrap = $(this);
			var maxPhotos = parseInt($wrap.attr('data-max'), 10);
			if (!maxPhotos) {
				maxPhotos = 5;
			}

			$wrap.on('click', '.example-project-gallery-admin__add', function (event) {
				event.preventDefault();

				if (typeof wp === 'undefined' || !wp.media) {
					return;
				}

				var currentCount = $wrap.find('.example-project-gallery-admin__item').length;
				var remaining = maxPhotos - currentCount;
				if (remaining <= 0) {
					return;
				}

				var frame = wp.media({
					title: 'Select project photos',
					button: { text: 'Add to gallery' },
					library: { type: 'image' },
					multiple: remaining > 1 ? 'add' : false
				});

				frame.on('select', function () {
					var selection = frame.state().get('selection');
					var $list = $wrap.find('.example-project-gallery-admin__list');
					var added = 0;

					selection.each(function (attachmentModel) {
						if (added >= remaining) {
							return;
						}

						var attachment = attachmentModel.toJSON();
						var attachmentId = parseInt(attachment.id, 10);
						if (!attachmentId) {
							return;
						}

						if ($wrap.find('.example-project-gallery-admin__item[data-id="' + attachmentId + '"]').length) {
							return;
						}

						var thumbUrl = attachment.url;
						if (attachment.sizes && attachment.sizes.thumbnail) {
							thumbUrl = attachment.sizes.thumbnail.url;
						}

						var $item = $('<li class="example-project-gallery-admin__item"></li>').attr('data-id', attachmentId);
						$item.append($('<img>').attr('src', thumbUrl).attr('alt', ''));
						$item.append(
							$('<button type="button" class="button-link example-project-gallery-admin__remove"></button>').text('Remove')
						);
						$list.append($item);
						added += 1;
					});

					syncProjectGalleryInput($wrap);
				});

				frame.open();
			});

			$wrap.on('click', '.example-project-gallery-admin__remove', function (event) {
				event.preventDefault();
				$(this).closest('.example-project-gallery-admin__item').remove();
				syncProjectGalleryInput($wrap);
			});
		});
	}

	$(document).ready(function () {
		initProjectGalleryAdmin();
	});
})(jQuery);
