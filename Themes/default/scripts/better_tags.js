(function () {
	'use strict';

	function normalize(value) {
		return value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
	}

	function splitTags(value) {
		return value.split(',').map(function (tag) { return tag.trim(); }).filter(Boolean);
	}

	function initBetterTags(root) {
		var hidden = null;

		// The submitted value field is rendered immediately before the visual
		// Better Tags widget in both SMF posting forms and Better Gallery forms.
		if (root.previousElementSibling && root.previousElementSibling.matches('input[name="better_tags"], input[name="better_tags_gallery_album"], input[name="better_tags_gallery_media"], input[name="better_tags_download"]'))
		{
			hidden = root.previousElementSibling;
			hidden.type = 'hidden';
		}
		else
		{
			hidden = root.querySelector('input[type="hidden"]');
		}

		if (!hidden)
			return;

		var input = root.querySelector('input[type="text"]');
		var suggestions = root.querySelector('.better-tags-suggestions');
		if (!input || !suggestions)
			return;

		var suggestUrl = root.getAttribute('data-suggest-url');
		var timer = null;
		var activeIndex = -1;
		var items = [];
		var selected = [];

		function setSelected(value) {
			selected = splitTags(value);
			hidden.value = selected.join(', ');
			input.value = '';
			renderChips();
		}

		function renderChips() {
			var old = root.querySelector('.better-tags-chips');
			if (old)
				old.remove();
			var wrap = document.createElement('div');
			wrap.className = 'better-tags-chips';
			selected.forEach(function (tag) {
				var chip = document.createElement('span');
				chip.className = 'better-tags-chip';
				chip.textContent = tag;
				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'better-tags-remove';
				remove.setAttribute('aria-label', 'Remove ' + tag);
				remove.textContent = 'Ô';
				remove.addEventListener('click', function () {
					selected = selected.filter(function (v) { return normalize(v) !== normalize(tag); });
					hidden.value = selected.join(', ');
					renderChips();
				});
				chip.appendChild(remove);
				wrap.appendChild(chip);
		});
			root.insertBefore(wrap, input);
		}

		function hideSuggestions() {
			suggestions.hidden = true;
			suggestions.innerHTML = '';
			items = [];
			activeIndex = -1;
		}

		function renderSuggestions(rows) {
			suggestions.innerHTML = '';
			items = [];
			activeIndex = -1;
			rows.forEach(function (row) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'better-tags-suggestion';
				button.textContent = row.name;
				button.addEventListener('mousedown', function (event) {
					event.preventDefault();
					addTag(row.name);
				});
				suggestions.appendChild(button);
				items.push(button);
		});
			suggestions.hidden = rows.length === 0;
		}

		function updateActive() {
			items.forEach(function (item, index) {
				item.classList.toggle('is-active', index === activeIndex);
			});
		}

		function addTag(tag) {
			tag = tag.trim();
			if (!tag)
				return;
			var norm = normalize(tag);
			if (selected.some(function (v) { return normalize(v) === norm; })) {
				input.value = '';
			hideSuggestions();
				return;
			}
			selected.push(tag);
			hidden.value = selected.join(', ');
			input.value = '';
			renderChips();
			hideSuggestions();
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var query = input.value.trim();
			if (query.length < 1) {
				hideSuggestions();
				return;
			}
			timer = setTimeout(function () {
				fetch(suggestUrl + '&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
					.then(function (response) { return response.json(); })
					.then(function (data) {
						renderSuggestions((data && Array.isArray(data.tags)) ? data.tags : []);
					})
					.catch(hideSuggestions);
			}, 180);
		});
		input.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' && items.length) {
				event.preventDefault();
				activeIndex = (activeIndex + 1) % items.length;
				updateActive();
		} else if (event.key === 'ArrowUp' && items.length) {
				event.preventDefault();
				activeIndex = (activeIndex - 1 + items.length) % items.length;
				updateActive();
		} else if (event.key === 'Enter') {
				event.preventDefault();
				if (activeIndex >= 0 && items[activeIndex])
					addTag(items[activeIndex].textContent);
				else
					addTag(input.value);
			} else if (event.key === ',' && input.value.trim()) {
				event.preventDefault();
				addTag(input.value);
			} else if (event.key === 'Escape') {
				hideSuggestions();
			}
		});

		setSelected(hidden.value);
	}

	function scanBetterTags(root) {
		(root || document).querySelectorAll('.better-tags-entry').forEach(function (node) {
			if (node.dataset.betterTagsInit === '1')
				return;
			node.dataset.betterTagsInit = '1';
			initBetterTags(node);
		});
	}


	// Theme Lab and other dynamic layout layers can move/paint posting widgets after
	// DOMContentLoaded. A one-shot scan is not enough, so keep a small, bounded
	// observer for newly-added Better Tags widgets. The data-better-tags-init flag
	// keeps it idempotent.
	function startBetterTagsObserver() {
		if (typeof MutationObserver === 'undefined' || !document.body)
			return;

		var scheduled = false;
		var observer = new MutationObserver(function (mutations) {
			var shouldScan = mutations.some(function (mutation) {
				return mutation.addedNodes && mutation.addedNodes.length;
			});
			if (!shouldScan || scheduled)
				return;
			scheduled = true;
			requestAnimationFrame(function () {
				scheduled = false;
				scanBetterTags(document);
			});
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}

	function initAll() {
		scanBetterTags(document);
		startBetterTagsObserver();
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', initAll);
	else
		initAll();
})();
