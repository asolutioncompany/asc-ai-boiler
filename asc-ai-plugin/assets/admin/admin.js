(function ($) {
	'use strict';

	$(document).ready(function () {
		initSyncBatches();
	});

	function sprintf(template) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return template
			.replace(/%[1-9]\$s/g, function (match) {
				var idx = parseInt(match.substring(1, match.length - 2), 10) - 1;
				if (idx < 0 || idx >= args.length) {
					return '';
				}
				return String(args[idx]);
			})
			.replace(/%s/g, function () {
				if (i >= args.length) {
					return '';
				}
				var v = args[i];
				i += 1;
				return String(v);
			});
	}

	function initSyncBatches() {
		if (typeof asc_ai_boiler_admin === 'undefined' || !asc_ai_boiler_admin.sync) {
			return;
		}

		var sync = asc_ai_boiler_admin.sync;
		var ajaxUrl = asc_ai_boiler_admin.ajax_url;
		var str = sync.strings;
		var $importProgress = $('#asc-ai-boiler-import-progress');
		var $importMessages = $('#asc-ai-boiler-import-messages');
		var $importStatus = $('#asc-ai-boiler-import-status');
		var $exportProgress = $('#asc-ai-boiler-export-progress');
		var $exportMessages = $('#asc-ai-boiler-export-messages');
		var $exportStatus = $('#asc-ai-boiler-export-status');
		var $importBtn = $('#asc-ai-boiler-import-submit');
		var $exportBtn = $('#asc-ai-boiler-export-submit');
		var $detectBtn = $('#asc-ai-boiler-detect-difference');
		var $diffBox = $('#asc-ai-boiler-diff-highlight');
		var $confirm = $('#asc-ai-boiler-import-confirm');
		var $syncBlock = $('#asc-ai-boiler-sync-block');
		var importAutoConfirm = false;
		if (sync.import_auto_confirm) {
			importAutoConfirm = true;
		}
		if ($syncBlock.length && $syncBlock.attr('data-asc-ai-boiler-import-auto-confirm') === '1') {
			importAutoConfirm = true;
		}
		if (importAutoConfirm && $confirm.length) {
			$confirm.prop('checked', true);
		}

		function setRunning(running) {
			$importBtn.prop('disabled', running);
			$exportBtn.prop('disabled', running);
			$detectBtn.prop('disabled', running);
		}

		function renderSummaryBar($container, summary) {
			if (!summary || !$container || !$container.length) {
				return;
			}
			$container.find('> .asc-ai-boiler-diff-summary-bar').remove();

			var $summaryBar = $('<div class="asc-ai-boiler-diff-summary-bar"></div>');
			var pagesCount = summary.pages_scanned || 0;
			var partialsCount = summary.partials_scanned || 0;
			var postsCount = summary.posts_scanned || 0;
			var imagesCount = summary.images_scanned || 0;

			var pagesText = (str.pages_scanned || 'Pages Scanned');
			var partialsText = (str.partials_scanned || 'Partials Scanned');
			var postsText = (str.posts_scanned || 'Posts & CPTs Scanned');
			var imagesText = (str.images_scanned || 'Images Scanned');

			$summaryBar.append(
				$('<div class="asc-ai-boiler-summary-pill"></div>').html(
					'<span class="dashicons dashicons-admin-page"></span> <strong>' + pagesCount + '</strong> ' + pagesText
				)
			);
			$summaryBar.append(
				$('<div class="asc-ai-boiler-summary-pill"></div>').html(
					'<span class="dashicons dashicons-layout"></span> <strong>' + partialsCount + '</strong> ' + partialsText
				)
			);
			$summaryBar.append(
				$('<div class="asc-ai-boiler-summary-pill"></div>').html(
					'<span class="dashicons dashicons-admin-post"></span> <strong>' + postsCount + '</strong> ' + postsText
				)
			);
			$summaryBar.append(
				$('<div class="asc-ai-boiler-summary-pill"></div>').html(
					'<span class="dashicons dashicons-format-image"></span> <strong>' + imagesCount + '</strong> ' + imagesText
				)
			);

			$container.prepend($summaryBar);
		}

		function renderDiffResult(data) {
			$diffBox.empty();
			$diffBox.removeClass(
				'asc-ai-boiler-diff-highlight--loading asc-ai-boiler-diff-highlight--empty asc-ai-boiler-diff-highlight--has-items asc-ai-boiler-diff-highlight--error'
			);
			if (!data) {
				return;
			}

			if (data.summary) {
				renderSummaryBar($diffBox, data.summary);
			}

			if (data.in_sync) {
				$diffBox.addClass('asc-ai-boiler-diff-highlight--empty');
				$diffBox.append($('<p class="asc-ai-boiler-diff-summary"></p>').text(str.detect_none));
				return;
			}
			$diffBox.addClass('asc-ai-boiler-diff-highlight--has-items');
			$diffBox.append($('<h3 class="asc-ai-boiler-diff-heading"></h3>').text(str.detect_heading));
			var list = data.differences || [];
			list.forEach(function (row) {
				var $item = $('<div class="asc-ai-boiler-diff-item"></div>');
				var suggestKey = String(row.suggestion || 'unclear').toLowerCase();
				if (suggestKey !== 'export' && suggestKey !== 'import' && suggestKey !== 'unclear') {
					suggestKey = 'unclear';
				}
				if (row.is_minor_summary) {
					$item.addClass('asc-ai-boiler-diff-item--minor-summary');
				}
				$item.addClass('asc-ai-boiler-diff-item--suggest-' + suggestKey);

				var $header = $('<div class="asc-ai-boiler-diff-path-header"></div>');

				if (row.is_minor_summary) {
					$header.append('<span class="dashicons dashicons-info asc-ai-boiler-diff-icon asc-ai-boiler-diff-icon--minor" title="Minor Adjustments"></span>');
					$header.append($('<span class="asc-ai-boiler-diff-badge asc-ai-boiler-diff-badge--minor"></span>').text(str.minor_sync || 'Minor Sync'));
				} else if (suggestKey === 'import') {
					$header.append('<span class="dashicons dashicons-download asc-ai-boiler-diff-icon asc-ai-boiler-diff-icon--import" title="Import needed from plugin files"></span>');
					$header.append($('<span class="asc-ai-boiler-diff-badge asc-ai-boiler-diff-badge--import"></span>').text(str.import_needed || 'Import Needed'));
				} else if (suggestKey === 'export') {
					$header.append('<span class="dashicons dashicons-upload asc-ai-boiler-diff-icon asc-ai-boiler-diff-icon--export" title="Export needed to plugin files"></span>');
					$header.append($('<span class="asc-ai-boiler-diff-badge asc-ai-boiler-diff-badge--export"></span>').text(str.export_needed || 'Export Needed'));
				} else {
					$header.append('<span class="dashicons dashicons-warning asc-ai-boiler-diff-icon asc-ai-boiler-diff-icon--unclear" title="Review choices"></span>');
					$header.append($('<span class="asc-ai-boiler-diff-badge asc-ai-boiler-diff-badge--unclear"></span>').text(str.review_needed || 'Review Needed'));
				}

				$header.append($('<div class="asc-ai-boiler-diff-path"></div>').text(row.relative_path || ''));
				$item.append($header);

				var issues = row.issues || [];
				if (issues.length) {
					var $issues = $('<ul class="asc-ai-boiler-diff-issues"></ul>');
					issues.forEach(function (line) {
						$issues.append($('<li></li>').text(line));
					});
					$item.append($issues);
				}
				if (row.suggestion_note) {
					$item.append($('<p class="asc-ai-boiler-diff-suggestion"></p>').text(row.suggestion_note));
				}
				$diffBox.append($item);
			});
		}

		function appendDetailMessages($container, lines) {
			if (!lines || !lines.length) {
				return;
			}
			var $ul = $container.find('ul').first();
			if (!$ul.length) {
				$ul = $('<ul class="asc-ai-boiler-settings-page__sync-msg-list"></ul>');
				$container.append($ul);
			}
			lines.forEach(function (line) {
				$ul.append($('<li></li>').text(line));
			});
		}

		function clearSyncStatusHighlight($statusEl) {
			$statusEl.removeClass(
				'asc-ai-boiler-sync-status--export-running asc-ai-boiler-sync-status--export-done asc-ai-boiler-sync-status--export-error '
				+ 'asc-ai-boiler-sync-status--import-running asc-ai-boiler-sync-status--import-done asc-ai-boiler-sync-status--import-error'
			);
		}

		function importFailure(msg) {
			$importStatus.removeClass('asc-ai-boiler-sync-status--import-running').addClass('asc-ai-boiler-sync-status--import-error');
			$importProgress.text(sprintf(str.failure, msg));
			setRunning(false);
		}

		$detectBtn.on('click', function () {
			if (!$detectBtn.length || $detectBtn.prop('disabled')) {
				return;
			}
			$diffBox.empty();
			$diffBox.removeClass(
				'asc-ai-boiler-diff-highlight--loading asc-ai-boiler-diff-highlight--empty asc-ai-boiler-diff-highlight--has-items asc-ai-boiler-diff-highlight--error'
			);
			$diffBox.addClass('asc-ai-boiler-diff-highlight--loading');
			$diffBox.append($('<p class="asc-ai-boiler-diff-loading"></p>').text(str.detect_working));
			setRunning(true);
			$.post(ajaxUrl, {
				action: sync.detect_action,
				_ajax_nonce: sync.nonce
			}).done(function (res) {
				setRunning(false);
				$diffBox.removeClass('asc-ai-boiler-diff-highlight--loading');
				if (!res || !res.success || !res.data) {
					$diffBox.addClass('asc-ai-boiler-diff-highlight--error');
					$diffBox.append($('<p class="asc-ai-boiler-diff-summary"></p>').text(sprintf(str.detect_fail, 'invalid response')));
					return;
				}
				renderDiffResult(res.data);
			}).fail(function (xhr) {
				setRunning(false);
				$diffBox.removeClass('asc-ai-boiler-diff-highlight--loading');
				var msg = 'request failed';
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				$diffBox.addClass('asc-ai-boiler-diff-highlight--error');
				$diffBox.append($('<p class="asc-ai-boiler-diff-summary"></p>').text(sprintf(str.detect_fail, msg)));
			});
		});

		$importBtn.on('click', function () {
			if ($importBtn.prop('disabled')) {
				return;
			}
			if (!importAutoConfirm && (!$confirm.length || !$confirm.is(':checked'))) {
				window.alert(str.confirm_required);
				return;
			}

			clearSyncStatusHighlight($importStatus);
			$importStatus.addClass('asc-ai-boiler-sync-status--import-running');
			$importMessages.empty();
			$importProgress.text(str.import_starting || 'Import starting…');
			setRunning(true);
			var offset = 0;
			var totalUpdated = 0;

			function runBatch() {
				$.post(ajaxUrl, {
					action: sync.import_action,
					_ajax_nonce: sync.nonce,
					offset: offset,
					confirmed: '1'
				}).done(function (res) {
					if (!res || !res.success || !res.data) {
						$importStatus.removeClass('asc-ai-boiler-sync-status--import-running').addClass('asc-ai-boiler-sync-status--import-error');
						$importProgress.text(sprintf(str.failure, 'invalid response'));
						setRunning(false);
						return;
					}
					var d = res.data;
					if (d.summary) {
						renderSummaryBar($importStatus, d.summary);
					}
					appendDetailMessages($importMessages, d.messages || []);
					totalUpdated += d.updated_in_batch || 0;
					offset = d.next_offset;
					var totalJobs = d.total_jobs || 0;
					var processed = Math.min(offset, totalJobs);
					$importProgress.text(sprintf(str.import_progress, String(processed), String(totalJobs)));
					if (d.done) {
						$importStatus.removeClass('asc-ai-boiler-sync-status--import-running').addClass('asc-ai-boiler-sync-status--import-done');
						$importProgress.text(sprintf(str.import_complete, String(totalUpdated), String(totalJobs)));
						setRunning(false);
						return;
					}
					runBatch();
				}).fail(function (xhr) {
					var msg = 'request failed';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					importFailure(msg);
				});
			}

			runBatch();
		});

		$exportBtn.on('click', function () {
			if ($exportBtn.prop('disabled')) {
				return;
			}
			clearSyncStatusHighlight($exportStatus);
			$exportStatus.addClass('asc-ai-boiler-sync-status--export-running');
			$exportMessages.empty();
			$exportProgress.text(str.export_starting || 'Export starting…');
			setRunning(true);
			var typeIndex = 0;
			var postOffset = 0;
			var runningFiles = 0;
			var runningMeta = 0;

			function runBatch() {
				$.post(ajaxUrl, {
					action: sync.export_action,
					_ajax_nonce: sync.nonce,
					type_index: typeIndex,
					post_offset: postOffset
				}).done(function (res) {
					if (!res || !res.success || !res.data) {
						$exportStatus.removeClass('asc-ai-boiler-sync-status--export-running').addClass('asc-ai-boiler-sync-status--export-error');
						$exportProgress.text(sprintf(str.failure, 'invalid response'));
						setRunning(false);
						return;
					}
					var d = res.data;
					if (d.summary) {
						renderSummaryBar($exportStatus, d.summary);
					}
					appendDetailMessages($exportMessages, d.messages || []);
					var batchFiles = d.updated_in_batch || 0;
					var batchMeta = d.manifest_metadata_refreshed_in_batch || 0;
					runningFiles += batchFiles;
					runningMeta += batchMeta;
					typeIndex = d.type_index;
					postOffset = d.post_offset;
					if (d.done) {
						$exportStatus.removeClass('asc-ai-boiler-sync-status--export-running').addClass('asc-ai-boiler-sync-status--export-done');
						if (runningMeta > 0 && str.export_complete_with_meta) {
							$exportProgress.text(
								sprintf(str.export_complete_with_meta, String(runningFiles), String(runningMeta))
							);
						} else {
							$exportProgress.text(sprintf(str.export_complete, String(runningFiles)));
						}
						setRunning(false);
						return;
					}
					$exportProgress.text(
						sprintf(
							str.export_progress,
							String(batchFiles),
							String(batchMeta),
							String(runningFiles),
							String(runningMeta)
						)
					);
					runBatch();
				}).fail(function (xhr) {
					var msg = 'request failed';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$exportStatus.removeClass('asc-ai-boiler-sync-status--export-running').addClass('asc-ai-boiler-sync-status--export-error');
					$exportProgress.text(sprintf(str.failure, msg));
					setRunning(false);
				});
			}

			runBatch();
		});
	}
})(jQuery);
