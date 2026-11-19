import '../css/app.css';

import Alpine from 'alpinejs';
import htmx from 'htmx.org';

window.htmx = htmx;
window.Alpine = Alpine;

/**
 * Rich text editor (Tiptap).
 * Tiptap dimuat saat editor dibuka (dynamic import), supaya halaman toko
 * tidak ikut mengunduhnya. Instance editor disimpan di closure, bukan di state
 * Alpine, karena proxy reaktif Alpine merusak internal Tiptap.
 */
const commands = {
    bold: (c) => c.toggleBold(),
    italic: (c) => c.toggleItalic(),
    strike: (c) => c.toggleStrike(),
    h2: (c) => c.toggleHeading({ level: 2 }),
    h3: (c) => c.toggleHeading({ level: 3 }),
    bullet: (c) => c.toggleBulletList(),
    ordered: (c) => c.toggleOrderedList(),
    quote: (c) => c.toggleBlockquote(),
    undo: (c) => c.undo(),
    redo: (c) => c.redo(),
};

const marks = {
    bold: ['bold'],
    italic: ['italic'],
    strike: ['strike'],
    h2: ['heading', { level: 2 }],
    h3: ['heading', { level: 3 }],
    bullet: ['bulletList'],
    ordered: ['orderedList'],
    quote: ['blockquote'],
};

Alpine.data('richEditor', (initial = '') => {
    let editor = null;
    let destroyed = false;

    return {
        html: initial, // sebelum editor siap, nilai awal tetap ikut terkirim saat form disubmit
        tick: 0,

        async init() {
            const [{ Editor }, { default: StarterKit }] = await Promise.all([
                import('@tiptap/core'),
                import('@tiptap/starter-kit'),
            ]);

            if (destroyed) {
                return;
            }

            editor = new Editor({
                element: this.$refs.surface,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        link: false,
                        underline: false,
                    }),
                ],
                content: initial,
                editorProps: {
                    attributes: {
                        class: 'rich-content rich-editor-surface',
                        'aria-label': 'Isi teks',
                    },
                },
                onUpdate: ({ editor: e }) => {
                    this.html = e.isEmpty ? '' : e.getHTML();
                },
                onTransaction: () => {
                    this.tick++;
                },
            });

            this.html = editor.isEmpty ? '' : editor.getHTML();
        },

        destroy() {
            destroyed = true;
            if (editor) {
                editor.destroy();
                editor = null;
            }
        },

        run(name) {
            if (editor && commands[name]) {
                commands[name](editor.chain().focus()).run();
            }
        },

        active(name) {
            this.tick; // memicu pembaruan tampilan tombol
            if (!editor || !marks[name]) {
                return false;
            }

            return editor.isActive(...marks[name]);
        },
    };
});

/**
 * Select dengan pencarian.
 * options: [{ id, name }]
 */
Alpine.data('searchSelect', (options = [], selected = '', placeholder = 'Pilih') => ({
    options,
    value: selected === null || selected === undefined ? '' : String(selected),
    placeholder,
    query: '',
    open: false,
    cursor: 0,

    get current() {
        return this.options.find((o) => String(o.id) === this.value) ?? null;
    },

    get filtered() {
        const q = this.query.trim().toLowerCase();

        return q ? this.options.filter((o) => o.name.toLowerCase().includes(q)) : this.options;
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.query = '';
        this.cursor = Math.max(0, this.filtered.findIndex((o) => String(o.id) === this.value));
        this.open = true;
        this.$nextTick(() => this.$refs.search.focus());
    },

    close() {
        this.open = false;
    },

    pick(option) {
        this.value = String(option.id);
        this.close();
        this.$refs.trigger.focus();
    },

    move(step) {
        const total = this.filtered.length;
        if (total === 0) {
            return;
        }
        this.cursor = (this.cursor + step + total) % total;
        this.$nextTick(() => {
            const items = this.$refs.list.querySelectorAll('[role="option"]');
            items[this.cursor]?.scrollIntoView({ block: 'nearest' });
        });
    },

    choose() {
        const option = this.filtered[this.cursor];
        if (option) {
            this.pick(option);
        }
    },
}));

Alpine.start();