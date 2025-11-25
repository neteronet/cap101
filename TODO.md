Task: Make the sidebar logout nav link non-active and full width between right and left edges in sidebar.

Steps:
1. Adjust CSS in pages/farmer-planting_status.php (or ideally in css/sidebars.css) to ensure `.sidebar .sidebar-logout-btn` has width: 100% and no active styles.
2. Search and compare logout link markup in other relevant pages:
    - pages/farmer-dashboard.php
    - pages/municipal-dashboard.php
    - pages/admin-dashboard.php
    - other relevant pages where sidebar is present.
3. Update logout nav HTML in the above pages to match farmer-planting_status.php:
    - div with class `sidebar-logout` wrapping
    - <a> with class `sidebar-logout-btn` and no 'active' class
    - ensure href points to the respective logout page (e.g. admin-logout.php, municipal-logout.php, etc.)
4. Test visual appearance of sidebar logout on multiple pages for full width and no active style.
5. Final verification and tweaks if necessary.

Notes:
- Existing CSS in farmer-planting_status.php sets logout button width 100% and transparent background, changing color on hover.
- Double check no 'active' class applied to logout links anywhere.
