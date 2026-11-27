let activePrintFrame = null;

document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-print-report]');
    if (!link || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const url = new URL(link.href);
    if (url.origin !== window.location.origin) return;

    event.preventDefault();
    if (activePrintFrame) return;

    const frame = document.createElement('iframe');
    frame.title = 'Printable report';
    frame.hidden = true;
    const cleanup = function () {
        frame.remove();
        activePrintFrame = null;
        if (link.isConnected) link.focus({ preventScroll: true });
    };

    frame.addEventListener('load', function () {
        try {
            const report = frame.contentWindow;
            if (report.location.href !== url.href) throw new Error('Unexpected print page');
            report.addEventListener('afterprint', cleanup, { once: true });
            report.print();
        } catch (error) {
            cleanup();
            window.alert('The report could not be opened for printing. Please try again.');
        }
    }, { once: true });

    activePrintFrame = frame;
    frame.src = url.href;
    document.body.appendChild(frame);
});
