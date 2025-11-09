import.meta.glob(["../images/**/*"]);

Livewire.directive("warn-dirty", ({ el, directive, component, cleanup }) => {
    const action = el.getAttribute("wire:submit");
    const message =
        directive.expression || "You have unsaved changes. Leave anyway?";

    const getState = () => JSON.stringify(component.ephemeral);
    let initialState = getState();

    const isDirty = () => getState() !== initialState;

    // Reset form state after a successful submit (no validation errors)
    component.$wire.intercept(action, ({ onSuccess }) => {
        onSuccess(({ payload }) => {
            const noErrors = payload.snapshot.memo.errors.length === 0;
            if (noErrors) initialState = getState();
        });
    });

    const handleBeforeUnload = (e) => {
        if (!isDirty()) return;
        e.preventDefault();
        e.returnValue = ""; // required for browser dialog
    };

    const handleNavigate = (e) => {
        if (isDirty() && !confirm(message)) e.preventDefault();
    };

    // Register listeners
    window.addEventListener("beforeunload", handleBeforeUnload);
    document.addEventListener("livewire:navigate", handleNavigate);

    // Clean up when component is torn down
    cleanup(() => {
        window.removeEventListener("beforeunload", handleBeforeUnload);
        document.removeEventListener("livewire:navigate", handleNavigate);
    });
});
