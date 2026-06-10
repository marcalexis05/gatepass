<?php
$system_name = get_setting('system_name', 'GatePass Pro');
?>
    </main>

    <footer class="w-full glass-panel border-t border-slate-800/80 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between text-slate-400 text-sm">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                <span>&copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($system_name); ?></strong>. All rights reserved.</span>
            </div>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <span class="flex items-center text-emerald-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-ping"></span>
                    System Online
                </span>
                <a href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../admin/login.php' : 'admin/login.php'; ?>" class="hover:text-white transition-colors">
                    Admin Portal
                </a>
            </div>
        </div>
    </footer>
    
    <!-- Custom Scripts -->
    <script src="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../assets/js/main.js' : 'assets/js/main.js'; ?>"></script>
</body>
</html>
