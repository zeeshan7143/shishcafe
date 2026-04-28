(function () {
    function initShishCafeUserLocationToggle() {
        var roleField = document.getElementById('role');
        var locationWrap = document.getElementById('shish-cafe-location-wrap');

        if (!roleField || !locationWrap) {
            return;
        }

        var managerRole = locationWrap.getAttribute('data-manager-role') || 'Orders Manager';
        var locationInput = document.getElementById('shish-cafe-user-location');

        function toggleLocationField() {
            var isManager = roleField.value === managerRole;
            locationWrap.style.display = isManager ? '' : 'none';
            
            // Clear location selection if switching away from manager role
            if (!isManager && locationInput && locationInput.value !== '') {
                locationInput.value = '';
            }
        }

        // Listen for role changes
        roleField.addEventListener('change', toggleLocationField);
        
        // Initial evaluation - use setTimeout to ensure DOM is fully ready
        setTimeout(function() {
            toggleLocationField();
        }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShishCafeUserLocationToggle);
    } else {
        initShishCafeUserLocationToggle();
    }
})();
