// Gatepass Pro Global Scripts
document.addEventListener('DOMContentLoaded', () => {
    // Utility functions for UI animations and notifications
    console.log("GatePass Pro client-side engine loaded.");
    
    // Auto-dismiss alert notifications after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 500ms ease';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
