    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        <p style="font-size: 11px; color: #666; margin-top: 5px;">Version 2.5.0 - Training Role System 🎓</p>

        <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
        <div style="margin-top: 15px; text-align: center;">
            <a href="bug_report.php" class="btn btn-primary btn-small" style="font-size: 12px; padding: 8px 16px;">
                🐛 Report a Bug
            </a>
        </div>
        <?php endif; ?>
    </footer>

    <!-- Latest Updates Widget -->
    <div id="latestUpdatesWidget" class="latest-updates-widget">
        <!-- Toggle Button -->
        <div id="updatesToggleBtn" class="updates-toggle-btn" onclick="toggleLatestUpdates()" title="Latest Updates">
            <span class="updates-icon">📢</span>
            <span class="updates-text">Latest Updates</span>
            <span id="updatesArrow" class="updates-arrow">▲</span>
        </div>

        <!-- Content Panel -->
        <div id="updatesContent" class="updates-content">
            <div class="updates-header">
                <h4>📢 Latest Updates</h4>
                <button class="updates-close-btn" onclick="toggleLatestUpdates()" title="Close">×</button>
            </div>

            <div class="updates-list">
                <!-- Version 2.5.0 -->
                <div class="update-item">
                    <div class="update-version">v2.5.0</div>
                    <div class="update-title">Training Role System</div>
                    <div class="update-features">
                        <div class="feature-item">🎓 Added comprehensive training role system for new staff</div>
                        <div class="feature-item">📊 Training progress bar in header for training users</div>
                        <div class="feature-item">📚 Admin interface for creating and managing training courses</div>
                        <div class="feature-item">👥 User training dashboard with progress tracking</div>
                        <div class="feature-item">🔒 Post creation restricted to Admins and Super Admins</div>
                        <div class="feature-item">📈 Training history preservation when users revert roles</div>
                        <div class="feature-item">⏱️ Time requirements per training content item</div>
                    </div>
                    <div class="update-date">2025-11-06</div>
                </div>

                <!-- Version 2.4.3 -->
                <div class="update-item">
                    <div class="update-version">v2.4.3</div>
                    <div class="update-title">Latest Updates Widget</div>
                    <div class="update-features">
                        <div class="feature-item">✨ Added expandable Latest Updates widget in bottom-right corner</div>
                        <div class="feature-item">✨ Shows version history with features and fixes</div>
                        <div class="feature-item">🎨 Smooth animations and professional styling</div>
                        <div class="feature-item">📱 Mobile responsive design</div>
                        <div class="feature-item">📋 Easy documentation for future updates</div>
                    </div>
                    <div class="update-date">2025-11-05</div>
                </div>

                <!-- Version 2.4.2 -->
                <div class="update-item">
                    <div class="update-version">v2.4.2</div>
                    <div class="update-title">PDF/DOCX Inline Preview System</div>
                    <div class="update-features">
                        <div class="feature-item">✨ Added inline PDF preview with expandable viewer</div>
                        <div class="feature-item">✨ Added dual file upload system (download vs preview)</div>
                        <div class="feature-item">🔧 Enhanced file categorization in database</div>
                        <div class="feature-item">🎨 Improved post attachment organization</div>
                    </div>
                    <div class="update-date">2025-11-05</div>
                </div>

                <!-- Version 2.4.1 -->
                <div class="update-item">
                    <div class="update-version">v2.4.1</div>
                    <div class="update-title">Auto-Creator Access for Restricted Subcategories</div>
                    <div class="update-features">
                        <div class="feature-item">🔒 Creators automatically have access to their restricted content</div>
                        <div class="feature-item">👥 Improved user selection interface for sharing</div>
                        <div class="feature-item">🎯 Fixed issue where creators could lock themselves out</div>
                    </div>
                    <div class="update-date">2025-11-05</div>
                </div>

                <!-- Version 2.4.0 -->
                <div class="update-item">
                    <div class="update-version">v2.4.0</div>
                    <div class="update-title">Enhanced Search System + Admin UI Security</div>
                    <div class="update-features">
                        <div class="feature-item">🔍 Added autocomplete search with top 3 results dropdown</div>
                        <div class="feature-item">🔍 Enhanced search with FULLTEXT and LIKE fallback</div>
                        <div class="feature-item">🔒 Critical admin security fixes implemented</div>
                        <div class="feature-item">👤 Super Admin role protection and UI button hiding</div>
                        <div class="feature-item">🛡️ Enhanced role-based access control</div>
                    </div>
                    <div class="update-date">2025-11-05</div>
                </div>

                <!-- Version 2.3.x -->
                <div class="update-item">
                    <div class="update-version">v2.3.x</div>
                    <div class="update-title">Earlier Updates</div>
                    <div class="update-features">
                        <div class="feature-item">🐛 Bug fixes and performance improvements</div>
                        <div class="feature-item">🎨 UI enhancements and styling updates</div>
                        <div class="feature-item">📱 Mobile responsiveness improvements</div>
                    </div>
                    <div class="update-date">Previous versions</div>
                </div>
            </div>

            <div class="updates-footer">
                <small style="color: #999;">Click the arrow button to collapse • Updates added when new features are released</small>
            </div>
        </div>
    </div>

    <style>
        /* Latest Updates Widget Styles */
        .latest-updates-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .updates-toggle-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            font-weight: 500;
            font-size: 14px;
        }

        .updates-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .updates-icon {
            font-size: 16px;
        }

        .updates-text {
            flex: 1;
        }

        .updates-arrow {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .updates-content {
            position: absolute;
            bottom: 100%;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform-origin: bottom right;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .updates-content.collapsed {
            opacity: 0;
            visibility: hidden;
            transform: scale(0.8) translateY(20px);
        }

        .updates-content.expanded {
            opacity: 1;
            visibility: visible;
            transform: scale(1) translateY(0);
        }

        .updates-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .updates-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .updates-close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background 0.2s ease;
        }

        .updates-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .updates-list {
            max-height: 380px;
            overflow-y: auto;
            padding: 0;
        }

        .update-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .update-item:hover {
            background-color: #f8f9fa;
        }

        .update-item:last-child {
            border-bottom: none;
        }

        .update-version {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .update-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .update-features {
            margin-bottom: 6px;
        }

        .feature-item {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 3px;
        }

        .update-date {
            font-size: 11px;
            color: #999;
            font-style: italic;
        }

        .updates-footer {
            padding: 12px 20px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }

        /* Custom scrollbar for updates list */
        .updates-list::-webkit-scrollbar {
            width: 6px;
        }

        .updates-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .updates-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .updates-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .latest-updates-widget {
                bottom: 10px;
                right: 10px;
                left: 10px;
            }

            .updates-content {
                width: 100%;
                max-width: 350px;
                right: auto;
                left: 0;
            }

            .updates-toggle-btn {
                padding: 10px 14px;
                font-size: 13px;
            }
        }
    </style>

    <script>
        // Latest Updates functionality
        let isUpdatesExpanded = false;

        function toggleLatestUpdates() {
            const content = document.getElementById('updatesContent');
            const arrow = document.getElementById('updatesArrow');

            isUpdatesExpanded = !isUpdatesExpanded;

            if (isUpdatesExpanded) {
                content.classList.remove('collapsed');
                content.classList.add('expanded');
                arrow.textContent = '▼';
            } else {
                content.classList.remove('expanded');
                content.classList.add('collapsed');
                arrow.textContent = '▲';
            }
        }

        // Initialize the widget in collapsed state
        document.addEventListener('DOMContentLoaded', function() {
            const content = document.getElementById('updatesContent');
            const arrow = document.getElementById('updatesArrow');

            // Start collapsed
            content.classList.add('collapsed');
            arrow.textContent = '▲';
        });

        // Close updates when clicking outside
        document.addEventListener('click', function(event) {
            const widget = document.getElementById('latestUpdatesWidget');
            const toggleBtn = document.getElementById('updatesToggleBtn');

            if (isUpdatesExpanded && !widget.contains(event.target)) {
                toggleLatestUpdates();
            }
        });
    </script>

    <!-- Admin Menu JavaScript (Admin Only) -->
    <?php
    require_once 'user_helpers.php';
    if (is_admin()):
    ?>
    <script>
        function toggleDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            if (overlay.classList.contains('show')) {
                closeDevMenu();
            } else {
                openDevMenu();
            }
        }

        function openDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            // Show overlay and menu
            overlay.classList.add('show');
            menu.classList.add('show');

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Add escape key listener
            document.addEventListener('keydown', handleEscapeKey);
        }

        function closeDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            overlay.classList.remove('show');
            menu.classList.remove('show');

            // Restore body scroll
            document.body.style.overflow = '';

            // Remove escape key listener
            document.removeEventListener('keydown', handleEscapeKey);
        }

        function handleEscapeKey(event) {
            if (event.key === 'Escape') {
                closeDevMenu();
            }
        }

        // Set up overlay click handler
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('devDropdownOverlay');
            if (overlay) {
                overlay.addEventListener('click', closeDevMenu);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
