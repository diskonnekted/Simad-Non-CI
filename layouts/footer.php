</main>
        
        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-8">
            <div class="max-w-screen-xl ml-0 px-2 sm:px-4 lg:px-6 py-4">
                <div class="text-left">
                    <div class="text-sm text-gray-500">
                        Developed by <a href="#" class="text-primary-600 hover:text-primary-500">Clasnet.ID</a>
                    </div>
                </div>
            </div>
        </footer>
    </div> 

    <!-- Scripts -->
    <script>
    // Only load jQuery if not already loaded by page
    if (typeof window.skipFooterJQuery === 'undefined' && typeof jQuery === 'undefined') {
        document.write('<script src="js/jquery.min.js"><\/script>');
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/sweet-alert/sweet-alert.min.js"></script>

    <script>
        // Global functions
        function showLoading() {
            document.getElementById('loading-overlay').classList.remove('hidden');
            document.getElementById('loading-overlay').classList.add('flex');
        }

        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('hidden');
            document.getElementById('loading-overlay').classList.remove('flex');
        }

        // Confirmation for delete actions (generic)
        function confirmDeleteGeneric(message = 'Apakah Anda yakin ingin menghapus data ini?') {
            return confirm(message);
        }

        // Auto-hide alerts after 3 seconds (exclude statistics cards)
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                // Only hide elements with 'alert' class, not background colors used in cards
                if (alert.classList.contains('alert')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 3000);

        // Sidebar and dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
            const userMenuButton = document.getElementById('user-menu-button');
            const dropdownUser = document.getElementById('dropdown-user');

            // Mobile sidebar toggle
            if (toggleSidebarMobile) {
                toggleSidebarMobile.addEventListener('click', function() {
                    sidebar.classList.toggle('hidden');
                    sidebarBackdrop.classList.toggle('hidden');
                });
            }

            // Close sidebar when clicking backdrop
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    sidebar.classList.add('hidden');
                    sidebarBackdrop.classList.add('hidden');
                });
            }

            // User dropdown toggle
            if (userMenuButton && dropdownUser) {
                userMenuButton.addEventListener('click', function() {
                    dropdownUser.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !dropdownUser.contains(event.target)) {
                        dropdownUser.classList.add('hidden');
                    }
                });
            }
        });
    </script>

    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>

</body>
</html>