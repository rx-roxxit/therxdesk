/**
 * Inertia's infinite scroll remembers each element's page from a debounced
 * MutationObserver callback, which can run after the items element has left the
 * DOM (a list that becomes empty, a dialog that closes). Looking the element up
 * by selector then returns null and Inertia throws on `childNodes`, so this
 * keeps the last element it found and hands that back instead.
 */
export const stableItemsElement = (
    selector: string,
): (() => HTMLElement | null) => {
    let element: HTMLElement | null = null;

    return () => {
        const current = document.querySelector<HTMLElement>(selector);

        if (current !== null) {
            element = current;
        }

        return element;
    };
};
