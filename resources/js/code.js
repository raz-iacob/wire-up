import hljs from "highlight.js/lib/core";
import bash from "highlight.js/lib/languages/bash";
import php from "highlight.js/lib/languages/php";
import javascript from "highlight.js/lib/languages/javascript";
import typescript from "highlight.js/lib/languages/typescript";
import xml from "highlight.js/lib/languages/xml";
import css from "highlight.js/lib/languages/css";
import json from "highlight.js/lib/languages/json";
import yaml from "highlight.js/lib/languages/yaml";
import sql from "highlight.js/lib/languages/sql";
import markdown from "highlight.js/lib/languages/markdown";
import "highlight.js/styles/github-dark.css";

hljs.registerLanguage("bash", bash);
hljs.registerLanguage("php", php);
hljs.registerLanguage("javascript", javascript);
hljs.registerLanguage("typescript", typescript);
hljs.registerLanguage("xml", xml);
hljs.registerLanguage("css", css);
hljs.registerLanguage("json", json);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("markdown", markdown);
hljs.registerAliases(["html"], { languageName: "xml" });

const highlight = () => {
    document
        .querySelectorAll("code[data-highlight]:not([data-highlighted])")
        .forEach((el) => hljs.highlightElement(el));
};

document.addEventListener("DOMContentLoaded", highlight);
document.addEventListener("livewire:navigated", highlight);
highlight();
