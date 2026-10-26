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
		// Better Tags widget in both SMF posting fields and external integrations
		// such as Better Gallery. It is a sibling, not a child of `root`.
		if (root.previousElementSibling && root.previousElementSibling.classList.contains('better_tags_value'))
			hidden = root.previousElementSibling;

		// Defensive fallbacks for custom templates/integrations.
		if (!hidden && root.parentElement)
			hidden = root.parentElement.querySelector('.better_tags_value');

		if (!hidden)
			hidden = document.getElementById('better_tags');
		var input = root.querySelector('.better_tags_entry_input');
		var selectedBox = root.querySelector('.better_tags_selected');
		var suggestions = root.querySelector('.better_tags_suggestions');
		if (!hidden || !input || !selectedBox || !suggestions) return;

		var maxTags = parseInt(root.dataset.maxTags || '8', 10);
		var maxLength = parseInt(root.dataset.maxLength || '40', 10);
		var suggestUrl = root.dataset.suggestUrl || '';
		var placeholder = root.dataset.placeholder || 'Type a tag and press Enter';
		var tags = [];
		var initialSuggestions = [];
		var preload = root.querySelector('.better_tags_preload');
		try {
			initialSuggestions = preload ? JSON.parse(preload.textContent || '[]') : [];
		} catch (e) {
			initialSuggestions = [];
		}
		var activeIndex = -1;
		var controller = null;
		var debounceTimer = null;

		function sync() {
			hidden.value = tags.join(', ');
			selectedBox.innerHTML = '';
			tags.forEach(function (tag, index) {
				var chip = document.createElement('span');
				chip.className = 'better_tags_edit_chip';
				var label = document.createElement('span');
				label.className = 'better_tags_chip_label';
				label.textContent = tag;
				chip.appendChild(label);

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'better_tags_chip_remove';
				remove.setAttribute('aria-label', 'Remove ' + tag);
				remove.textContent = '×';
				remove.addEventListener('click', function () {
					tags.splice(index, 1);
					sync();
					input.focus();
				});

				chip.appendChild(remove);
				selectedBox.appendChild(chip);
			});

			input.placeholder = tags.length ? '' : placeholder;
			input.disabled = tags.length >= maxTags;
			root.classList.toggle('is-full', input.disabled);
		}

		function addTag(value) {
			var tag = value.trim().replace(/^#+|,+$/g, '').replace(/\s+/g, ' ');
			if (!tag || tags.length >= maxTags) return false;
			if (tag.length > maxLength) tag = tag.slice(0, maxLength).trim();
			if (!tag) return false;

			var key = normalize(tag);
			if (tags.some(function (existing) { return normalize(existing) === key; })) {
				input.value = '';
				closeSuggestions();
				return false;
			}

			tags.push(tag);
			input.value = '';
			closeSuggestions();
			sync();
			return true;
		}

		function closeSuggestions() {
			suggestions.hidden = true;
			suggestions.innerHTML = '';
			activeIndex = -1;
			input.setAttribute('aria-expanded', 'false');
		}

		function activate(index) {
			var items = suggestions.querySelectorAll('.better_tags_suggestion');
			items.forEach(function (item) { item.classList.remove('is-active'); });
			if (!items.length) return;
			if (index < 0) index = items.length - 1;
			if (index >= items.length) index = 0;
			activeIndex = index;
			items[index].classList.add('is-active');
			items[index].scrollIntoView({ block: 'nearest' });
		}

		function renderSuggestions(items) {
			var selectedKeys = new Set(tags.map(normalize));
			items = items.filter(function (item) { return !selectedKeys.has(normalize(item.tag_name)); });
			if (!items.length) {
				closeSuggestions();
				return;
			}

			suggestions.innerHTML = '';
			items.forEach(function (item, index) {
				var option = document.createElement('button');
				option.type = 'button';
				option.className = 'better_tags_suggestion';
				option.setAttribute('role', 'option');

				var name = document.createElement('span');
				name.className = 'better_tags_suggestion_name';
				name.textContent = item.tag_name;
				option.appendChild(name);

				var count = document.createElement('span');
				count.className = 'better_tags_suggestion_count';
				var usageCount = typeof item.usage_count === 'number' ? item.usage_count : (item.topic_count || 0);
				count.textContent = usageCount + (usageCount === 1 ? ' use' : ' uses');
				option.appendChild(count);

				option.addEventListener('mousedown', function (event) { event.preventDefault(); });
				option.addEventListener('click', function () {
					addTag(item.tag_name);
					input.focus();
				});
				option.addEventListener('mouseenter', function () { activate(index); });
				suggestions.appendChild(option);
			});

			suggestions.hidden = false;
			input.setAttribute('aria-expanded', 'true');
			activeIndex = -1;
		}

		function localSuggestions(query) {
			var key = normalize(query);

			return initialSuggestions
				.filter(function (item) {
					return !key || normalize(item.tag_name).indexOf(key) !== -1;
				})
				.sort(function (a, b) {
					var ak = normalize(a.tag_name);
					var bk = normalize(b.tag_name);
					var ar = ak === key ? 0 : (ak.indexOf(key) === 0 ? 1 : 2);
					var br = bk === key ? 0 : (bk.indexOf(key) === 0 ? 1 : 2);
					if (ar !== br) return ar - br;
					if ((b.topic_count || 0) !== (a.topic_count || 0)) return (b.topic_count || 0) - (a.topic_count || 0);
					return a.tag_name.localeCompare(b.tag_name);
				})
				.slice(0, 10);
		}

		function fetchSuggestions() {
			var query = input.value.trim();

			if (!query || tags.length >= maxTags) {
				closeSuggestions();
				return;
			}

			var local = localSuggestions(query);
			if (local.length)
				renderSuggestions(local);
			else
				closeSuggestions();

			// Empty input intentionally shows the most-used visible tags from the
			// server-rendered preload. Only call the endpoint after the user types.
			if (!query || !suggestUrl)
				return;

			if (controller) controller.abort();
			controller = new AbortController();

			fetch(suggestUrl + '&q=' + encodeURIComponent(query), {
				credentials: 'same-origin',
				signal: controller.signal,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
				.then(function (response) { return response.ok ? response.json() : []; })
				.then(function (items) {
					if (Array.isArray(items) && items.length)
						renderSuggestions(items);
					else if (!local.length)
						closeSuggestions();
				})
				.catch(function (error) {
					if (error.name !== 'AbortError' && !local.length)
						closeSuggestions();
				});
		}

		splitTags(hidden.value).forEach(function (tag) {
			if (!tags.some(function (existing) { return normalize(existing) === normalize(tag); }) && tags.length < maxTags)
				tags.push(tag);
		});
		sync();

		input.addEventListener('input', function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(fetchSuggestions, 140);
		});


		input.addEventListener('keydown', function (event) {
			var items = suggestions.querySelectorAll('.better_tags_suggestion');
			if (event.key === 'ArrowDown' && !suggestions.hidden) {
				event.preventDefault();
				activate(activeIndex + 1);
				return;
			}
			if (event.key === 'ArrowUp' && !suggestions.hidden) {
				event.preventDefault();
				activate(activeIndex - 1);
				return;
			}
			if ((event.key === 'Enter' || event.key === ',') && input.value.trim()) {
				event.preventDefault();
				if (!suggestions.hidden && activeIndex >= 0 && items[activeIndex])
					addTag(items[activeIndex].querySelector('.better_tags_suggestion_name').textContent);
				else
					addTag(input.value);
				return;
			}
			if (event.key === 'Backspace' && !input.value && tags.length) {
				tags.pop();
				sync();
				return;
			}
			if (event.key === 'Escape') closeSuggestions();
		});

		input.addEventListener('blur', function () {
			setTimeout(closeSuggestions, 120);
		});

		root.addEventListener('click', function (event) {
			if (event.target === root || event.target === selectedBox) input.focus();
		});

		var form = hidden.form;
		if (form) {
			form.addEventListener('submit', function () {
				if (input.value.trim()) addTag(input.value);
				hidden.value = tags.join(', ');
			});
		}
	}

	function initAllBetterTags() {
		document.querySelectorAll('.better_tags_entry').forEach(function (root) {
			if (root.dataset.betterTagsInitialized === '1')
				return;

			root.dataset.betterTagsInitialized = '1';
			initBetterTags(root);
		});
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', initAllBetterTags, { once: true });
	else
		initAllBetterTags();
})();
