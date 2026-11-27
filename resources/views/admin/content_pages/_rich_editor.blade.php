{{--
    The text editor of Website & App Pages: x-rich="section.body" gives a field
    a toolbar (bold, italic, underline, lists, link). The editor person sees
    formatting only. What is stored is the small text format the website and
    the app read: **bold**, *italic*, __underline__, "- " lists, "1." lists,
    [label](link); each line of the editor is one line of text.
--}}
<script>
(function () {
    const FORMATS = ['bold', 'italic', 'underline', 'link', 'list'];
    const TOOLBAR = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];
    const INLINE = /\*\*(.+?)\*\*(?!\*)|__(.+?)__|(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])|\[([^\]]+)\]\(([^)\s]+)\)/;
    const LINE = /^\s*(?:([-*•])|(\d+)[.)])\s+(.*)$/;

    function inlineOps(text, attributes, ops) {
        let rest = text;
        while (rest) {
            const m = INLINE.exec(rest);
            if (!m) { ops.push({ insert: rest, attributes: { ...attributes } }); return; }
            if (m.index > 0) ops.push({ insert: rest.slice(0, m.index), attributes: { ...attributes } });
            if (m[1] !== undefined) inlineOps(m[1], { ...attributes, bold: true }, ops);
            else if (m[2] !== undefined) inlineOps(m[2], { ...attributes, underline: true }, ops);
            else if (m[3] !== undefined) inlineOps(m[3], { ...attributes, italic: true }, ops);
            else inlineOps(m[4], { ...attributes, link: m[5] }, ops);
            rest = rest.slice(m.index + m[0].length);
        }
    }

    function marksToDelta(text) {
        const ops = [];
        String(text ?? '').replace(/\r\n?/g, '\n').split('\n').forEach((line) => {
            const m = LINE.exec(line);
            inlineOps(m ? m[3] : line, {}, ops);
            ops.push(m ? { insert: '\n', attributes: { list: m[1] ? 'bullet' : 'ordered' } } : { insert: '\n' });
        });
        return { ops };
    }

    // Spaces stay outside the marks, which would not close around them.
    function wrap(text, attributes) {
        const [, before, middle, after] = /^(\s*)([\s\S]*?)(\s*)$/.exec(text);
        if (!middle) return text;
        let out = middle;
        if (attributes.italic) out = `*${out}*`;
        if (attributes.underline) out = `__${out}__`;
        if (attributes.bold) out = `**${out}**`;
        if (attributes.link) {
            const link = /^(https?:|mailto:|tel:|\/|#)/i.test(attributes.link) ? attributes.link : `https://${attributes.link}`;
            out = `[${out}](${link})`;
        }
        return before + out + after;
    }

    function deltaToMarks(delta) {
        const lines = [];
        let line = '';
        let number = 0;
        delta.ops.forEach((op) => {
            if (typeof op.insert !== 'string') return;
            const attributes = op.attributes ?? {};
            const parts = op.insert.split('\n');
            parts.forEach((part, i) => {
                if (part) line += wrap(part, attributes);
                if (i === parts.length - 1) return;
                const list = attributes.list;
                number = list === 'ordered' ? number + 1 : 0;
                lines.push(list === 'bullet' ? `- ${line}` : list === 'ordered' ? `${number}. ${line}` : line);
                line = '';
            });
        });
        if (line) lines.push(line);
        return lines.join('\n').replace(/\s+$/, '');
    }

    document.addEventListener('alpine:init', () => {
        Alpine.directive('rich', (el, { expression }, { evaluateLater, effect, cleanup }) => {
            const read = evaluateLater(expression);
            const write = evaluateLater(`${expression} = __rich`);
            let quill = null;
            let value = '';

            const load = () => {
                const legacy = /<\/?(p|br|strong|em|b|i|u|ul|ol|li|a|span|div)\b/i.test(value);
                quill.setContents(legacy ? quill.clipboard.convert({ html: value }) : marksToDelta(value), 'silent');
            };
            const start = () => {
                el.classList.add('quill-wrapper', 'quill-compact');
                // Quill puts its toolbar before the element it is given: both stay in the field's box.
                const host = document.createElement('div');
                el.appendChild(host);
                quill = new Quill(host, { theme: 'snow', formats: FORMATS, bounds: el, modules: { toolbar: TOOLBAR } });
                load();
                quill.on('text-change', (_d, _o, source) => {
                    if (source !== 'user') return;
                    value = deltaToMarks(quill.getContents());
                    write(() => {}, { scope: { __rich: value } });
                });
            };
            // Long pages hold many fields: an editor starts when its field is first seen.
            const seen = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting)) { seen.disconnect(); start(); }
            });
            el.style.minHeight = '5rem';
            seen.observe(el);
            cleanup(() => seen.disconnect());

            effect(() => read((v) => {
                const next = v ?? '';
                if (next === value) return;
                value = next;
                if (quill) load();
            }));
        });
    });
})();
</script>
