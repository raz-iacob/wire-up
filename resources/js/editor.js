import { Mark, mergeAttributes } from "@tiptap/core";

// Rich-text editor enhancements (Tiptap-dependent). Loaded only on screens that
// render a Flux editor, so the heavy @tiptap/core code stays out of the main
// admin bundle.

// Inline rich-text badge: wraps selected text in a coloured pill. Registered on
// every Flux editor below. Background/text colours round-trip through data
// attributes (the exact chosen value) plus an inline style (what the browser
// paints), so the source view and colour pickers can recover the original hex.
const Badge = Mark.create({
    name: "badge",
    inclusive: false,

    addAttributes() {
        return {
            bg: {
                default: null,
                parseHTML: (el) => el.getAttribute("data-badge-bg"),
                renderHTML: (attrs) =>
                    attrs.bg
                        ? {
                              "data-badge-bg": attrs.bg,
                              style: `background-color:${attrs.bg}`,
                          }
                        : {},
            },
            color: {
                default: null,
                parseHTML: (el) => el.getAttribute("data-badge-color"),
                renderHTML: (attrs) =>
                    attrs.color
                        ? {
                              "data-badge-color": attrs.color,
                              style: `color:${attrs.color}`,
                          }
                        : {},
            },
        };
    },

    parseHTML() {
        return [{ tag: "span[data-badge]" }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            "span",
            mergeAttributes(HTMLAttributes, {
                "data-badge": "",
                class: "wire-badge",
            }),
            0,
        ];
    },

    addCommands() {
        return {
            setBadge:
                (attrs = {}) =>
                ({ commands }) =>
                    commands.setMark(this.name, attrs),
            unsetBadge:
                () =>
                ({ commands }) =>
                    commands.unsetMark(this.name),
        };
    },
});

// Inline text colour. Theme tokens render as var(--wire-*) so the colour flips
// with the light/dark palette; a custom hex is kept verbatim for the rare case
// that needs one. Only whitelisted tokens resolve, so nothing arbitrary reaches
// the style attribute.
const COLOR_TOKENS = {
    accent: "--wire-accent",
    muted: "--wire-muted",
    text: "--wire-body-text",
};

const TextColor = Mark.create({
    name: "textColor",
    inclusive: false,

    addAttributes() {
        return {
            token: {
                default: null,
                parseHTML: (el) => el.getAttribute("data-color-token"),
                renderHTML: (attrs) =>
                    attrs.token ? { "data-color-token": attrs.token } : {},
            },
            color: {
                default: null,
                parseHTML: (el) => el.getAttribute("data-color"),
                renderHTML: (attrs) =>
                    attrs.color ? { "data-color": attrs.color } : {},
            },
        };
    },

    parseHTML() {
        return [{ tag: "span[data-color-token]" }, { tag: "span[data-color]" }];
    },

    renderHTML({ HTMLAttributes }) {
        const token = HTMLAttributes["data-color-token"];
        const value = COLOR_TOKENS[token]
            ? `var(${COLOR_TOKENS[token]})`
            : HTMLAttributes["data-color"];

        return [
            "span",
            mergeAttributes(
                HTMLAttributes,
                value ? { style: `color:${value}` } : {},
            ),
            0,
        ];
    },

    addCommands() {
        return {
            setTextColor:
                (attrs = {}) =>
                ({ commands }) =>
                    commands.setMark(this.name, {
                        token: attrs.token ?? null,
                        color: attrs.token ? null : (attrs.color ?? null),
                    }),
            unsetTextColor:
                () =>
                ({ commands }) =>
                    commands.unsetMark(this.name),
        };
    },
});

// Strips formatting from pasted content before it enters a rich-text editor:
// block wrappers become paragraphs, headings below the three the toolbar offers
// are lifted to h3, every other tag is unwrapped to its text, and only
// paragraphs, headings, line breaks, lists and links survive. Keeps pastes from
// dragging in <strong>, inline styles, fonts, etc.
const PASTE_ALLOWED_TAGS = new Set([
    "P",
    "BR",
    "UL",
    "OL",
    "LI",
    "A",
    "H1",
    "H2",
    "H3",
]);

function cleanPastedHtml(html) {
    const doc = new DOMParser().parseFromString(html, "text/html");

    const clean = (node) => {
        [...node.children].forEach((el) => {
            clean(el);

            const tag = el.tagName;

            if (/^H[4-6]$/.test(tag)) {
                const heading = doc.createElement("h3");
                heading.append(...el.childNodes);
                el.replaceWith(heading);
            } else if (tag === "DIV") {
                const paragraph = doc.createElement("p");
                paragraph.append(...el.childNodes);
                el.replaceWith(paragraph);
            } else if (!PASTE_ALLOWED_TAGS.has(tag)) {
                el.replaceWith(...el.childNodes);
            } else {
                [...el.attributes].forEach((attr) => {
                    if (!(tag === "A" && attr.name === "href")) {
                        el.removeAttribute(attr.name);
                    }
                });
            }
        });
    };

    clean(doc.body);

    return doc.body.innerHTML;
}

window.cleanPastedHtml = cleanPastedHtml;

document.addEventListener("flux:editor", (e) => {
    e.detail.registerExtensions([Badge, TextColor]);

    e.detail.init(({ editor }) => {
        const root = (editor.options?.element ?? editor.view?.dom)?.closest(
            "[data-flux-editor]",
        );
        if (root) root._tiptap = editor;

        editor.on("create", () => {
            editor.view.dom.addEventListener(
                "paste",
                (event) => {
                    const clipboard = event.clipboardData;
                    if (!clipboard) return;

                    const html = clipboard.getData("text/html");
                    const text = clipboard.getData("text/plain");
                    if (!html && !text) return;

                    event.preventDefault();

                    editor
                        .chain()
                        .focus()
                        .insertContent(html ? cleanPastedHtml(html) : text)
                        .run();
                },
                true,
            );
        });
    });
});
