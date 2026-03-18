/* SalesIQ shop.js */
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss success alerts after 4 s
    document.querySelectorAll('.alert-success').forEach(function (el) {
        setTimeout(function () {
            var a = bootstrap.Alert.getOrCreateInstance(el);
            if (a) a.close();
        }, 4000);
    });
});
