import.meta.glob(["../images/**/*"], {
    eager: true,
    query: "?url",
    import: "default",
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
});
