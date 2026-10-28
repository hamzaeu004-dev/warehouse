import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import stockForm from './stock-form';

Alpine.data('stockForm', stockForm);

// Back button: go to the previous screen if we moved inside the app,
// otherwise (page opened directly or refreshed) go to the parent screen.
let movedInsideApp = false;
document.addEventListener('livewire:navigate', () => (movedInsideApp = true));
window.goBack = (fallbackUrl) => {
    if (movedInsideApp && history.length > 1) {
        history.back();
    } else {
        Livewire.navigate(fallbackUrl);
    }
};

Livewire.start();
