document.addEventListener('DOMContentLoaded', function() {
    const easyMDE = new EasyMDE({
        element: document.getElementById('editor'),
        spellChecker: false,
        autosave: {
            enabled: true,
            uniqueId: "mdEditor",
            delay: 1000,
        },
    });

    document.querySelectorAll('.copy-url').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const copyUrlModal = new bootstrap.Modal(document.getElementById('copyUrlModal'));
                copyUrlModal.show();
            });
        });
    });

    document.querySelectorAll('.file-info, .view-md').forEach(element => {
        element.addEventListener('click', function() {
            const filename = this.getAttribute('data-file');
            const timestamp = new Date().getTime();
            fetch(`files/${encodeURIComponent(filename)}?t=${timestamp}`)
                .then(response => response.text())
                .then(markdown => {
                    const html = marked(markdown);
                    document.getElementById('viewModalBody').innerHTML = html;
                    document.getElementById('viewModalLabel').textContent = `Viewing: ${filename}`;
                    document.getElementById('modalEditBtn').href = `?edit=${encodeURIComponent(filename)}`;
                    document.getElementById('modalCopyUrlBtn').setAttribute('data-url', `${window.location.origin}${window.location.pathname}files/${encodeURIComponent(filename)}`);
                    document.getElementById('modalViewHtmlBtn').href = `view_html.php?file=${encodeURIComponent(filename)}`;
                    document.querySelector('#viewModal .delete-file').setAttribute('data-file', filename);
                });
        });
    });

    document.getElementById('modalCopyUrlBtn').addEventListener('click', function() {
        const url = this.getAttribute('data-url');
        navigator.clipboard.writeText(url).then(() => {
            const copyUrlModal = new bootstrap.Modal(document.getElementById('copyUrlModal'));
            copyUrlModal.show();
        });
    });

    document.querySelectorAll('.delete-file').forEach(button => {
        button.addEventListener('click', function(e) {
            const filename = this.getAttribute('data-file');
            document.getElementById('deleteFilename').value = filename;
            document.getElementById('deleteModalLabel').textContent = `Confirm Deletion: ${filename}`;
        });
    });

    // Accordion functionality
    const accordion = document.getElementById('olderFilesAccordion');
    if (accordion) {
        accordion.addEventListener('show.bs.collapse', function () {
            this.querySelector('.accordion-button').classList.remove('collapsed');
        });

        accordion.addEventListener('hide.bs.collapse', function () {
            this.querySelector('.accordion-button').classList.add('collapsed');
        });
    }
});