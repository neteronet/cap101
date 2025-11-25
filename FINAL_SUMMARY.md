Task: Make the sidebar logout nav link non-active and full width between right and left edges in sidebar.

Summary of Actions Taken:
- Updated CSS in css/sidebars.css to ensure the logout nav link (.sidebar-logout-btn) is full width with no active styling.
- Verified that only pages/farmer-planting_status.php uses a sidebar logout nav link with class sidebar-logout-btn.
- Other pages use logout buttons in the fixed header with class logout-btn; these were left unchanged.
- Sidebar logout nav link structure on farmer-planting_status.php confirmed as:
  - div.sidebar-logout wrapping
  - <a> with class sidebar-logout-btn without 'active' class
  - Proper full width CSS styling applied.

Next Steps:
- Review visual appearance on farmer-planting_status.php and other relevant pages.
- Confirm logout link appearance is as desired: full width and not visually active in the sidebar.
- Keep header logout buttons consistent on other pages.

This concludes the requested changes for the logout sidebar styling.
