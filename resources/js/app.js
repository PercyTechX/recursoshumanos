import './bootstrap';

import SignaturePad from 'signature_pad';

window.SignaturePad = SignaturePad;

// Componente Alpine para firmar con el dedo. Sincroniza la firma (PNG dataURL)
// con una propiedad del componente Livewire.
window.firmaPad = function (prop) {
    return {
        pad: null,
        init() {
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            this.pad = new window.SignaturePad(canvas, { penColor: '#10233A' });
            this.pad.addEventListener('endStroke', () => {
                this.$wire.set(prop, this.pad.toDataURL('image/png'), false);
            });
        },
        limpiar() {
            this.pad.clear();
            this.$wire.set(prop, '', false);
        },
    };
};
