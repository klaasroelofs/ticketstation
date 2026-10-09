/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Edit screen of a mail template (view=templates, layout=form): inserts a placeholder at the
 * cursor when it is clicked, shows a preview and sends a test mail of what is typed in the
 * form (TemplatesController::preview() and ::testmail(); nothing is saved), and puts the default
 * text back after a confirmation (::resetdefault()).
 */
(() => {
    'use strict';

    const options = Joomla.getOptions('com_ticketstation.templates');
    const form = document.getElementById('adminForm');
    if (!options || !form) {
        return;
    }

    const subject = document.getElementById('mailsubject');

    const editor = () => (Joomla.editors && Joomla.editors.instances ? Joomla.editors.instances.mailbody : null);

    const bodyValue = () => {
        const instance = editor();

        return instance ? instance.getValue() : form.elements.mailbody.value;
    };

    // The form as it is now, with the body as the editor holds it, aimed at one controller task
    const request = (task) => {
        const data = new FormData(form);
        data.set('mailbody', bodyValue());
        data.set('task', task);

        return fetch(options.url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then((response) => {
            if (!response.ok) {
                throw new Error(response.statusText);
            }

            return response.json();
        });
    };

    const notify = (type, message) => {
        Joomla.removeMessages();
        Joomla.renderMessages({ [type]: [message] });
    };

    // Clicking a placeholder inserts it where the cursor is: in the subject when that has the
    // focus, otherwise in the body. mousedown is cancelled so the cursor keeps its place.
    document.querySelectorAll('.ts-template-insert').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            const tag = button.dataset.tag;

            if (subject && document.activeElement === subject) {
                const start = subject.selectionStart;
                const end = subject.selectionEnd;
                subject.setRangeText(tag, start, end, 'end');
                subject.focus();

                return;
            }

            const instance = editor();
            if (instance && instance.replaceSelection) {
                instance.replaceSelection(tag);
            } else {
                const area = form.elements.mailbody;
                area.setRangeText(tag, area.selectionStart, area.selectionEnd, 'end');
                area.focus();
            }
        });
    });

    const modalElement = document.getElementById('ts-template-preview-modal');
    const frame = document.getElementById('ts-template-preview-frame');
    const previewSubject = document.getElementById('ts-template-preview-subject');
    const previewError = document.getElementById('ts-template-preview-error');

    // Fetches the mail as it is typed now and shows it in the frame of the modal
    const loadPreview = () => {
        previewError.classList.add('d-none');

        return request('preview')
            .then((answer) => {
                previewSubject.textContent = answer.subject;
                // The sandbox keeps scripts and forms in the mail out of the admin
                frame.srcdoc = '<div style="font-family:sans-serif">' + answer.body + '</div>';
            })
            .catch(() => {
                frame.srcdoc = '';
                previewError.classList.remove('d-none');
            });
    };

    document.getElementById('ts-template-preview')?.addEventListener('click', () => {
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }

        loadPreview();
    });

    document.getElementById('ts-template-preview-refresh')?.addEventListener('click', loadPreview);

    document.getElementById('ts-template-testmail')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        request('testmail')
            .then((answer) => notify(answer.ok ? 'message' : 'error', answer.message))
            .catch(() => notify('error', options.failed))
            .finally(() => {
                button.disabled = false;
            });
    });

    document.getElementById('ts-template-reset')?.addEventListener('click', () => {
        if (window.confirm(options.confirmReset)) {
            Joomla.submitbutton('resetdefault');
        }
    });
})();
