<?php
/**
 * Notification Bell Component
 * 
 * This file contains the HTML, CSS, and JavaScript for the notification bell
 * feature. Include this file in farmer pages to add the notification functionality.
 * 
 * USAGE:
 * 1. Include this file in the <head> section: <?php include '../includes/notification_bell.php'; ?>
 * 2. Add the notification bell HTML in the header (see below)
 * 3. The JavaScript will automatically handle fetching and displaying notifications
 */

// Only output if not already included
if (!defined('NOTIFICATION_BELL_INCLUDED')) {
    define('NOTIFICATION_BELL_INCLUDED', true);
?>

<!-- SweetAlert2 for Toast Notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Notification Bell Styles -->
<style>
    /* Notification Bell Container */
    .notification-bell-container {
        position: relative;
        display: inline-block;
        margin-right: 15px;
    }

    /* Notification Bell Icon */
    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 1.3rem;
        color: #19860f;
        padding: 8px 12px;
        border-radius: 50%;
        transition: all 0.3s ease;
        background: transparent;
        border: none;
    }

    .notification-bell:hover {
        background-color: #f0f0f0;
        transform: scale(1.1);
    }

    /* Notification Badge */
    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        border: 2px solid white;
        min-width: 20px;
    }

    .notification-badge.hidden {
        display: none;
    }

    /* Notification Dropdown */
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 380px;
        max-height: 500px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 1070;
        display: none;
        overflow: hidden;
        margin-top: 8px;
    }

    .notification-dropdown.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Notification Header */
    .notification-header {
        padding: 15px;
        background: #19860f;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #ddd;
    }

    .notification-header h6 {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
    }

    .notification-header .mark-all-read {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-header .mark-all-read:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Notification List */
    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #e8f5e9;
        border-left: 3px solid #19860f;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
        font-size: 14px;
    }

    .notification-message {
        color: #666;
        font-size: 13px;
        margin-bottom: 4px;
        line-height: 1.4;
    }

    .notification-time {
        color: #999;
        font-size: 11px;
    }

    /* Empty State */
    .notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: #999;
    }

    .notification-empty i {
        font-size: 3rem;
        margin-bottom: 10px;
        color: #ddd;
    }

    /* Loading State */
    .notification-loading {
        padding: 20px;
        text-align: center;
        color: #999;
    }

    /* Scrollbar Styling */
    .notification-list::-webkit-scrollbar {
        width: 6px;
    }

    .notification-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .notification-list::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .notification-list::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<!-- Notification Bell HTML (Place this in your header) -->
<!--
<div class="notification-bell-container">
    <button class="notification-bell" id="notificationBell" onclick="toggleNotificationDropdown()">
        <i class="fas fa-bell"></i>
        <span class="notification-badge hidden" id="notificationBadge">0</span>
    </button>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
            <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="notification-loading">Loading notifications...</div>
        </div>
    </div>
</div>
-->

<!-- Notification Bell JavaScript -->
<script>
    // Global variables
    let notificationCheckInterval = null;
    let lastUnreadCount = 0;
    let isDropdownOpen = false;

    /**
     * Fetch notifications from the server
     */
    function fetchNotifications() {
        // Get the base path - works for pages in 'pages/' directory
        const apiPath = window.location.pathname.includes('/pages/') 
            ? 'api/fetch_notifications.php' 
            : 'pages/api/fetch_notifications.php';
        fetch(apiPath)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    updateNotificationList(data.notifications);
                    
                    // Show toast for new notifications
                    if (data.unread_count > lastUnreadCount && lastUnreadCount > 0) {
                        const newCount = data.unread_count - lastUnreadCount;
                        showNotificationToast(newCount);
                    }
                    
                    lastUnreadCount = data.unread_count;
                } else {
                    console.error('Error fetching notifications:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
    }

    /**
     * Update the notification badge count
     */
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    /**
     * Update the notification list in the dropdown
     */
    function updateNotificationList(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notification => {
            const unreadClass = notification.status === 'unread' ? 'unread' : '';
            const createdAt   = notification.created_at || '';
            const timeLabel   = notification.time || '';
            html += `
                <div class="notification-item ${unreadClass}" data-id="${notification.id}">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time" title="${createdAt}">
                        ${timeLabel}${createdAt ? ' • ' + createdAt : ''}
                    </div>
                </div>
            `;
        });

        list.innerHTML = html;
    }

    /**
     * Toggle notification dropdown
     */
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;

        isDropdownOpen = !isDropdownOpen;
        
        if (isDropdownOpen) {
            dropdown.classList.add('show');
            // Mark all as read when dropdown is opened
            markAllAsRead();
            // Fetch fresh notifications
            fetchNotifications();
        } else {
            dropdown.classList.remove('show');
        }
    }

    /**
     * Mark all notifications as read
     */
    function markAllAsRead() {
        // Get the base path - works for pages in 'pages/' directory
        const apiPath = window.location.pathname.includes('/pages/') 
            ? 'api/mark_as_read.php' 
            : 'pages/api/mark_as_read.php';
        fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh notifications to update the UI
                fetchNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }

    /**
     * Show toast notification for new notifications
     */
    function showNotificationToast(count) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'New Notification' + (count > 1 ? 's' : ''),
                text: `You have ${count} new notification${count > 1 ? 's' : ''}`,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    }

    /**
     * Close dropdown when clicking outside
     */
    document.addEventListener('click', function(event) {
        const bellContainer = document.querySelector('.notification-bell-container');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (bellContainer && dropdown && isDropdownOpen) {
            if (!bellContainer.contains(event.target)) {
                dropdown.classList.remove('show');
                isDropdownOpen = false;
            }
        }
    });

    /**
     * Initialize notification system
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch notifications immediately
        fetchNotifications();
        
        // Set up interval to check for new notifications every 10 seconds
        notificationCheckInterval = setInterval(fetchNotifications, 10000);
    });

    /**
     * Cleanup interval on page unload
     */
    window.addEventListener('beforeunload', function() {
        if (notificationCheckInterval) {
            clearInterval(notificationCheckInterval);
        }
    });
</script>

<?php
} // End of NOTIFICATION_BELL_INCLUDED check
?>

