import { readonly, ref } from 'vue';

const isOpen = ref(false);

const open = (): void => {
    isOpen.value = true;
};

const close = (): void => {
    isOpen.value = false;
};

/**
 * The Support chat sheet is mounted once in the app layout, so the Help menu
 * and the sheet share one open state.
 */
export const useSupportChat = () => ({
    isOpen: readonly(isOpen),
    open,
    close,
});
