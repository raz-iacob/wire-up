import "./passkeys";

import.meta.glob(["../images/**/*"], {
    eager: true,
    query: "?url",
    import: "default",
});

document.addEventListener("livewire:navigate", (event) => {
    if (/^\/admin(\/|$)/.test(event.detail.url.pathname)) {
        event.preventDefault();
        window.location.assign(event.detail.url.href);
    }
});

document.addEventListener("alpine:init", () => {
    // x-swipe: dispatches a `swipe-left` / `swipe-right` event on the element for
    // horizontal touch swipes. Vertical drags and short taps are ignored so page
    // scrolling and taps keep working. Pair with x-on:swipe-left / x-on:swipe-right.
    Alpine.directive("swipe", (el, {}, { cleanup }) => {
        let startX = 0;
        let startY = 0;

        const onStart = (event) => {
            const touch = event.changedTouches[0];
            startX = touch.screenX;
            startY = touch.screenY;
        };

        const onEnd = (event) => {
            const touch = event.changedTouches[0];
            const dx = touch.screenX - startX;
            const dy = touch.screenY - startY;

            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                el.dispatchEvent(
                    new CustomEvent(dx < 0 ? "swipe-left" : "swipe-right"),
                );
            }
        };

        el.addEventListener("touchstart", onStart, { passive: true });
        el.addEventListener("touchend", onEnd, { passive: true });

        cleanup(() => {
            el.removeEventListener("touchstart", onStart);
            el.removeEventListener("touchend", onEnd);
        });
    });

    Alpine.data("turnstile", (siteKey) => ({
        widgetId: null,

        init() {
            loadTurnstile().then((turnstile) => {
                this.widgetId = turnstile.render(this.$el, {
                    sitekey: siteKey,
                    callback: (token) =>
                        this.$wire.set("turnstileToken", token, false),
                    "expired-callback": () =>
                        this.$wire.set("turnstileToken", "", false),
                    "error-callback": () =>
                        this.$wire.set("turnstileToken", "", false),
                });
            });
        },

        reset() {
            if (this.widgetId !== null && window.turnstile) {
                window.turnstile.reset(this.widgetId);
            }
        },

        destroy() {
            if (this.widgetId !== null && window.turnstile) {
                window.turnstile.remove(this.widgetId);
            }
        },
    }));
});

let turnstileLoader = null;

function loadTurnstile() {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    turnstileLoader ??= new Promise((resolve) => {
        const script = document.createElement("script");
        script.src =
            "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
        script.async = true;
        script.addEventListener("load", () => resolve(window.turnstile));
        document.head.appendChild(script);
    });

    return turnstileLoader;
}
