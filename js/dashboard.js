/**
 * Shared Dashboard Logic for Sistem-Satpam
 * Centralized Sidebar Toggle and Logout Confirmation
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Sidebar Toggle Logic (Mobile)
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // 2. Logout Confirmation Logic
    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
        btnLogout.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get logout URL from data-url attribute
            const logoutUrl = btnLogout.getAttribute('data-url');
            if (!logoutUrl) return;

            Swal.fire({
                title: 'Keluar dari Sistem?',
                text: "Anda akan mengakhiri sesi. Lanjutkan?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = logoutUrl;
                }
            });
        });
    }
});

/**
 * Shared confirmation for deletions
 * @param {string} url - The URL to redirect to if confirmed
 */
function konfirmasiHapus(url) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
