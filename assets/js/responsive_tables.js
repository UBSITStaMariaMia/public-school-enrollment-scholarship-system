const tableScrollContainers = Array.from(document.querySelectorAll(
    '.page-container :is(.table-container, .table-responsive, .table-scroll, .records-table-wrapper, ' +
    '.announcement-table-wrapper, .masterlist-table-wrap, .renewal-table-wrap, .stipend-table-wrap)'
)).filter(function (container) {
    return container.querySelector(':scope > table');
});

function updateTableScrollAccess(container) {
    const needsScroll = container.scrollWidth > container.clientWidth + 1;
    if (needsScroll) {
        container.tabIndex = 0;
        container.setAttribute('role', 'region');
        container.setAttribute('aria-label', 'Scrollable data table');
        container.setAttribute('data-scrollable-table', '');
    } else if (container.hasAttribute('data-scrollable-table')) {
        container.removeAttribute('tabindex');
        container.removeAttribute('role');
        container.removeAttribute('aria-label');
        container.removeAttribute('data-scrollable-table');
    }
}

if (tableScrollContainers.length) {
    const updateTables = function () {
        tableScrollContainers.forEach(updateTableScrollAccess);
    };
    updateTables();
    window.addEventListener('resize', updateTables);
    window.addEventListener('load', updateTables, { once: true });
}
