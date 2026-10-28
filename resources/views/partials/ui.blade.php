{{--
    Shared UI behaviour:
      <form data-confirm="Message" [data-confirm-title] [data-confirm-button] [data-confirm-tone="danger|primary"]>
        → styled confirmation dialog before submitting (falls back to the browser confirm without Alpine).
      Every non-AJAX form disables its submit buttons and shows a spinner once submitted,
      which prevents accidental double submissions (e.g. double conversion or double payment).
--}}
<div x-data="confirmDialog()" x-on:confirm-form.window="open($event.detail)" x-on:keydown.escape.window="cancel()"
     x-show="visible" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="fixed inset-0 bg-slate-900/50" x-show="visible" x-transition.opacity @click="cancel()"></div>
    <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl" x-show="visible" x-transition>
        <div class="flex gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                  :class="tone === 'danger' ? 'bg-red-100 text-red-600' : 'bg-indigo-100 text-indigo-600'">
                <x-icon name="alert" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h3 id="confirm-title" class="text-base font-semibold text-slate-900" x-text="title"></h3>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-600" x-text="message"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" @click="cancel()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="button" @click="accept()" x-ref="ok"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm"
                    :class="tone === 'danger' ? 'bg-red-600 hover:bg-red-500' : 'bg-indigo-600 hover:bg-indigo-500'" x-text="button"></button>
        </div>
    </div>
</div>

<style>
    .btn-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: ajax-spin .7s linear infinite; vertical-align: -0.15em; }
</style>
<script>
    function confirmDialog() {
        return {
            visible: false, title: '', message: '', button: 'Confirm', tone: 'primary', form: null, submitter: null,
            open({ form, submitter }) {
                this.form = form;
                this.submitter = submitter;
                this.title = form.dataset.confirmTitle || 'Are you sure?';
                this.message = form.dataset.confirm;
                this.button = form.dataset.confirmButton || 'Confirm';
                this.tone = form.dataset.confirmTone || 'danger';
                this.visible = true;
                this.$nextTick(() => this.$refs.ok.focus());
            },
            cancel() { this.visible = false; this.form = null; },
            accept() {
                const form = this.form;
                this.visible = false;
                if (!form) return;
                form.dataset.confirmed = '1';
                form.requestSubmit(this.submitter || undefined);
            },
        };
    }

    (() => {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.dataset.confirm && form.dataset.confirmed !== '1') {
                e.preventDefault();
                if (window.Alpine) {
                    window.dispatchEvent(new CustomEvent('confirm-form', { detail: { form, submitter: e.submitter } }));
                } else if (confirm(form.dataset.confirm)) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit(e.submitter || undefined);
                }
                return;
            }
            delete form.dataset.confirmed;

            // AJAX filter forms and GET forms stay interactive.
            if (form.hasAttribute('data-ajax-filter') || (form.method || 'get').toLowerCase() === 'get' || form.target === '_blank') return;

            // Disable after the browser has captured the form data (keeps the submitter's name/value),
            // unless a later handler (e.g. an inline confirm) cancelled the submission.
            setTimeout(() => {
                if (e.defaultPrevented) return;
                form.querySelectorAll('button[type=submit], button:not([type])').forEach((btn) => {
                    btn.disabled = true;
                    if (btn === e.submitter) btn.insertAdjacentHTML('afterbegin', '<span class="btn-spinner" aria-hidden="true"></span>');
                });
                form.setAttribute('aria-busy', 'true');
            }, 0);
        }, true);

        // Restore buttons when the page is shown from the back/forward cache.
        window.addEventListener('pageshow', (e) => {
            if (!e.persisted) return;
            document.querySelectorAll('form[aria-busy]').forEach((form) => {
                form.removeAttribute('aria-busy');
                form.querySelectorAll('button[disabled]').forEach((b) => { b.disabled = false; b.querySelector('.btn-spinner')?.remove(); });
            });
        });
    })();
</script>
