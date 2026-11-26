document.querySelectorAll('[data-student-suggestions]').forEach(function (input, index) {
    const options = document.createElement('datalist');
    options.id = 'student-search-suggestions-' + index;
    input.insertAdjacentElement('afterend', options);
    input.setAttribute('list', options.id);
    input.setAttribute('autocomplete', 'off');

    let timer;
    let pendingRequest;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        if (pendingRequest) pendingRequest.abort();
        options.replaceChildren();
        const search = input.value.trim();
        if (search.length < 2) return;

        timer = setTimeout(async function () {
            const request = new AbortController();
            pendingRequest = request;
            const form = input.form;
            const fields = form ? new FormData(form) : new FormData();
            const url = new URL(input.dataset.suggestionUrl, window.location.href);
            const params = new URLSearchParams({
                q: search,
                scope: input.dataset.studentSuggestions,
                field: fields.get('search_by') || 'name',
                grade: fields.get('grade') || '',
                section: fields.get('section') || '',
                status: fields.get('status') || ''
            });
            url.search = params.toString();

            try {
                const response = await fetch(url, { signal: request.signal, cache: 'no-store' });
                if (!response.ok) return;
                const suggestions = await response.json();
                if (!request.signal.aborted && input.value.trim() === search) {
                    options.replaceChildren(...suggestions.map(function (suggestion) {
                        const option = document.createElement('option');
                        option.value = suggestion.value;
                        if (suggestion.label !== suggestion.value) option.label = suggestion.label;
                        return option;
                    }));
                }
            } catch (error) {
                // The normal search still works if suggestions are unavailable.
            }
        }, 180);
    });

    if (input.form) {
        input.form.addEventListener('change', function (event) {
            if (event.target === input) return;
            clearTimeout(timer);
            if (pendingRequest) pendingRequest.abort();
            options.replaceChildren();
        });
    }
});
