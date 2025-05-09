document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form.checkout');
    console.log({form})
    if (form) {
        form.addEventListener('submit', function(e) {
            if (monzi_params.checkoutType === 'onpage' && monzi_params.redirectUrl !== '') {
                e.preventDefault();
                e.stopPropagation();

                var width = 600;
                var height = 800;
                var left = window.screenX + (window.outerWidth / 2) - (width / 2);
                var top = window.screenY + (window.outerHeight / 2.5) - (height / 2);

                var popup = window.open(monzi_params.redirectUrl, 'MonziPayment', `width=${width},height=${height},top=${top},left=${left},toolbar=no`);
                if (window.focus) {
                    popup.focus();
                }

                if (popup) {
                    popup.addEventListener('beforeunload', function(event) {
                        window.alert('Si cierras esta ventana deberás realizar el pago nuevamente')
                    });
                }

                return false;
            }
        }, true);
    }
});
