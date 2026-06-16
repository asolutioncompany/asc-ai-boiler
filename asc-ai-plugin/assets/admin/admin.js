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
		var $progress = $('#asc-ai-boiler-sync-progress');
		var $messages = $('#asc-ai-boiler-sync-messages');
		var $status = $('#asc-ai-boiler-sync-status');
		var $restoreBtn = $('#asc-ai-boiler-restore-submit');
		var $backupBtn = $('#asc-ai-boiler-backup-submit');
		var $detectBtn = $('#asc-ai-boiler-detect-difference');
		var $diffBox = $('#asc-ai-boiler-diff-highlight');
		var $confirm = $('#asc-ai-boiler-restore-confirm');
		var $syncBlock = $('#asc-ai-boiler-sync-block');
		var restoreAutoConfirm = false;
		if (sync.restore_auto_confirm) {
			restoreAutoConfirm = true;
		}
		if ($syncBlock.length && $syncBlock.attr('data-asc-ai-boiler-restore-auto-confirm') === '1') {
			restoreAutoConfirm = true;
		}
		if (restoreAutoConfirm && $confirm.length) {
			$confirm.prop('checked', true);
		}

		function setRunning(running) {
			$restoreBtn.prop('disabled', running);
			$backupBtn.prop('disabled', running);
			$detectBtn.prop('disabled', running);
		}

		function renderDiffResult(data) {
			$diffBox.empty();
			$diffBox.removeClass(
				'asc-ai-boiler-diff-highlight--loading asc-ai-boiler-diff-highlight--empty asc-ai-boiler-diff-highlight--has-items asc-ai-boiler-diff-highlight--error'
			);
			if (!data) {
				return;
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
				if (suggestKey !== 'backup' && suggestKey !== 'restore' && suggestKey !== 'unclear') {
					suggestKey = 'unclear';
				}
				$item.addClass('asc-ai-boiler-diff-item--suggest-' + suggestKey);
				$item.append($('<div class="asc-ai-boiler-diff-path"></div>').text(row.relative_path || ''));
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

		function appendDetailMessages(lines) {
			if (!lines || !lines.length) {
				return;
			}
			var $ul = $messages.find('ul').first();
			if (!$ul.length) {
				$ul = $('<ul class="asc-ai-boiler-settings-page__sync-msg-list"></ul>');
				$messages.append($ul);
			}
			lines.forEach(function (line) {
				$ul.append($('<li></li>').text(line));
			});
		}

		function clearSyncStatusHighlight() {
			$status.removeClass(
				'asc-ai-boiler-sync-status--backup-running asc-ai-boiler-sync-status--backup-done asc-ai-boiler-sync-status--backup-error '
					+ 'asc-ai-boiler-sync-status--restore-running asc-ai-boiler-sync-status--restore-done asc-ai-boiler-sync-status--restore-error'
			);
		}

		function restoreFailure(msg) {
			$status.removeClass('asc-ai-boiler-sync-status--restore-running').addClass('asc-ai-boiler-sync-status--restore-error');
			$progress.text(sprintf(str.failure, msg));
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

		$restoreBtn.on('click', function () {
			if ($restoreBtn.prop('disabled')) {
				return;
			}
			if (!restoreAutoConfirm && (!$confirm.length || !$confirm.is(':checked'))) {
				window.alert(str.confirm_required);
				return;
			}

			clearSyncStatusHighlight();
			$status.addClass('asc-ai-boiler-sync-status--restore-running');
			$messages.empty();
			$progress.text(str.restore_starting || 'Restore starting…');
			setRunning(true);
			var offset = 0;
			var totalUpdated = 0;

			function runBatch() {
				$.post(ajaxUrl, {
					action: sync.restore_action,
					_ajax_nonce: sync.nonce,
					offset: offset,
					confirmed: '1'
				}).done(function (res) {
					if (!res || !res.success || !res.data) {
						$status.removeClass('asc-ai-boiler-sync-status--restore-running').addClass('asc-ai-boiler-sync-status--restore-error');
						$progress.text(sprintf(str.failure, 'invalid response'));
						setRunning(false);
						return;
					}
					var d = res.data;
					appendDetailMessages(d.messages || []);
					totalUpdated += d.updated_in_batch || 0;
					offset = d.next_offset;
					var totalJobs = d.total_jobs || 0;
					var processed = Math.min(offset, totalJobs);
					$progress.text(sprintf(str.restore_progress, String(processed), String(totalJobs)));
					if (d.done) {
						$status.removeClass('asc-ai-boiler-sync-status--restore-running').addClass('asc-ai-boiler-sync-status--restore-done');
						$progress.text(sprintf(str.restore_complete, String(totalUpdated), String(totalJobs)));
						setRunning(false);
						return;
					}
					runBatch();
				}).fail(function (xhr) {
					var msg = 'request failed';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					restoreFailure(msg);
				});
			}

			runBatch();
		});

		$backupBtn.on('click', function () {
			if ($backupBtn.prop('disabled')) {
				return;
			}
			clearSyncStatusHighlight();
			$status.addClass('asc-ai-boiler-sync-status--backup-running');
			$messages.empty();
			$progress.text(str.backup_starting || 'Backup starting…');
			setRunning(true);
			var typeIndex = 0;
			var postOffset = 0;
			var runningFiles = 0;
			var runningMeta = 0;

			function runBatch() {
				$.post(ajaxUrl, {
					action: sync.backup_action,
					_ajax_nonce: sync.nonce,
					type_index: typeIndex,
					post_offset: postOffset
				}).done(function (res) {
					if (!res || !res.success || !res.data) {
						$status.removeClass('asc-ai-boiler-sync-status--backup-running').addClass('asc-ai-boiler-sync-status--backup-error');
						$progress.text(sprintf(str.failure, 'invalid response'));
						setRunning(false);
						return;
					}
					var d = res.data;
					appendDetailMessages(d.messages || []);
					var batchFiles = d.updated_in_batch || 0;
					var batchMeta = d.manifest_metadata_refreshed_in_batch || 0;
					runningFiles += batchFiles;
					runningMeta += batchMeta;
					typeIndex = d.type_index;
					postOffset = d.post_offset;
					if (d.done) {
						$status.removeClass('asc-ai-boiler-sync-status--backup-running').addClass('asc-ai-boiler-sync-status--backup-done');
						if (runningMeta > 0 && str.backup_complete_with_meta) {
							$progress.text(
								sprintf(str.backup_complete_with_meta, String(runningFiles), String(runningMeta))
							);
						} else {
							$progress.text(sprintf(str.backup_complete, String(runningFiles)));
						}
						setRunning(false);
						return;
					}
					$progress.text(
						sprintf(
							str.backup_progress,
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
					$status.removeClass('asc-ai-boiler-sync-status--backup-running').addClass('asc-ai-boiler-sync-status--backup-error');
					$progress.text(sprintf(str.failure, msg));
					setRunning(false);
				});
			}

			runBatch();
		});
	}
})(jQuery);
