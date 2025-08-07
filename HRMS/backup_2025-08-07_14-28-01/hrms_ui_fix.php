<?php
// HRMS UI Fix - Force CSS Styling for Sidebar
// This file should be included after the header in all HRMS files

// Check if this is being included from an HRMS file
$current_dir = dirname($_SERVER['SCRIPT_NAME']);
$is_hrms_page = strpos($current_dir, '/HRMS') !== false || strpos($current_dir, '\\HRMS') !== false;

if ($is_hrms_page) {
    echo '<style type="text/css">
    /* HRMS SIDEBAR FIX - Force proper styling with maximum specificity */
    body .sidebar,
    nav.sidebar,
    #sidebar,
    .sidebar {
        display: block !important;
        visibility: visible !important;
        background: white !important;
        border-right: 1px solid #e2e8f0 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        min-height: 100vh !important;
        width: 280px !important;
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        z-index: 1000 !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        transition: transform 0.3s ease !important;
        padding: 0 !important;
        margin: 0 !important;
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
    }
    
    body .sidebar.collapsed,
    nav.sidebar.collapsed,
    #sidebar.collapsed,
    .sidebar.collapsed {
        transform: translateX(-100%) !important;
    }
    
    /* Navigation links with maximum specificity */
    body .sidebar .nav-link,
    nav.sidebar .nav-link,
    #sidebar .nav-link,
    .sidebar .nav-link,
    .nav-link {
        color: #64748b !important;
        text-decoration: none !important;
        display: flex !important;
        align-items: center !important;
        padding: 0.75rem 1rem !important;
        font-weight: 500 !important;
        font-size: 0.875rem !important;
        transition: all 0.2s ease !important;
        border-left: 3px solid transparent !important;
        margin: 2px 8px !important;
        border-radius: 6px !important;
        background: transparent !important;
        border-top: none !important;
        border-right: none !important;
        border-bottom: none !important;
        line-height: 1.5 !important;
        min-height: 40px !important;
    }
    
    body .sidebar .nav-link:hover,
    nav.sidebar .nav-link:hover,
    #sidebar .nav-link:hover,
    .sidebar .nav-link:hover,
    .nav-link:hover {
        background: linear-gradient(90deg, #fcfcfd 0%, transparent 100%) !important;
        color: #2563eb !important;
        border-left-color: #3b82f6 !important;
        transform: translateX(2px) !important;
        text-decoration: none !important;
    }
    
    body .sidebar .nav-link.active,
    nav.sidebar .nav-link.active,
    #sidebar .nav-link.active,
    .sidebar .nav-link.active,
    .nav-link.active {
        background: linear-gradient(90deg, rgba(37, 99, 235, 0.08) 0%, transparent 100%) !important;
        color: #2563eb !important;
        border-left-color: #2563eb !important;
        font-weight: 600 !important;
    }
    
    /* Icons with maximum specificity */
    body .sidebar .nav-link i,
    nav.sidebar .nav-link i,
    #sidebar .nav-link i,
    .sidebar .nav-link i,
    .nav-link i,
    [class*="bi-"],
    [class^="bi-"],
    [class*="fas"],
    [class^="fas"] {
        width: 18px !important;
        margin-right: 0.75rem !important;
        font-size: 1rem !important;
        transition: all 0.2s ease !important;
        text-align: center !important;
        display: inline-block !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        line-height: 1 !important;
    }
    
    /* Force Bootstrap Icons font */
    [class^="bi-"], [class*=" bi-"] {
        font-family: "bootstrap-icons" !important;
        font-weight: normal !important;
    }
    
    body .sidebar .nav-link:hover i,
    nav.sidebar .nav-link:hover i,
    #sidebar .nav-link:hover i,
    .sidebar .nav-link:hover i,
    .nav-link:hover i {
        transform: scale(1.1) !important;
        color: #3b82f6 !important;
    }
    
    .nav-section-title {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 1.2px !important;
        color: #9ca3af !important;
        margin: 0.75rem 1rem 0.5rem 1rem !important;
        padding-top: 0.5rem !important;
        position: relative !important;
    }
    
    .nav-section-title::before {
        content: "" !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 1px !important;
        background: linear-gradient(90deg, #e2e8f0 0%, transparent 100%) !important;
    }
    
    /* HRMS Dropdown Styles */
    .dropdown-toggle {
        background: #f8fafc !important;
        border-radius: 6px !important;
        transition: all 0.2s ease !important;
        border-left: 2px solid transparent !important;
        color: #64748b !important;
        text-decoration: none !important;
        border: none !important;
        position: relative !important;
    }
    
    .dropdown-toggle:hover {
        background: #f1f5f9 !important;
        color: #334155 !important;
        transform: translateX(2px) !important;
        border-left: 2px solid #3b82f6 !important;
        text-decoration: none !important;
    }
    
    .dropdown-toggle[aria-expanded="true"] {
        background: #eff6ff !important;
        color: #1e40af !important;
        border-left: 2px solid #2563eb !important;
    }
    
    .dropdown-toggle::after {
        content: "" !important;
        display: inline-block !important;
        margin-left: auto !important;
        vertical-align: middle !important;
        border-top: 0.3em solid #64748b !important;
        border-right: 0.3em solid transparent !important;
        border-bottom: 0 !important;
        border-left: 0.3em solid transparent !important;
        transition: all 0.2s ease !important;
    }
    
    .dropdown-toggle[aria-expanded="true"]::after {
        transform: rotate(-180deg) !important;
        border-top-color: #2563eb !important;
    }
    
    .nav-submenu {
        background: #fafbfc !important;
        border-left: 2px solid #e1e7ef !important;
        margin-left: 16px !important;
        border-radius: 0 8px 8px 0 !important;
        padding: 4px 0 !important;
        overflow: hidden !important;
    }
    
    .nav-submenu .nav-link {
        padding: 10px 16px 10px 20px !important;
        font-size: 13px !important;
        color: #475569 !important;
        margin: 1px 8px !important;
        border-radius: 6px !important;
        font-weight: 400 !important;
        border-left: none !important;
    }
    
    .nav-submenu .nav-link:hover {
        background: #f1f5f9 !important;
        color: #334155 !important;
        border-left: 3px solid #3b82f6 !important;
        transform: translateX(2px) !important;
    }
    
    .nav-submenu .nav-link.active {
        background: #eff6ff !important;
        color: #1e40af !important;
        font-weight: 500 !important;
        border-left: 3px solid #2563eb !important;
    }
    
    .nav-submenu .nav-link i {
        width: 16px !important;
        text-align: center !important;
        margin-right: 8px !important;
        font-size: 0.875rem !important;
    }
    
    /* Main content adjustment for HRMS pages */
    
    
    .main-content.sidebar-collapsed {
        margin-left: 0 !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%) !important;
        }
        
        .sidebar.show {
            transform: translateX(0) !important;
        }
        
        
    }
    
    /* Force Bootstrap Icons to display */
    [class^="bi-"], [class*=" bi-"] {
        font-family: "Bootstrap Icons" !important;
        display: inline-block !important;
        vertical-align: middle !important;
    }
    
    /* Header fixes for HRMS */
    .header {
        margin-left: 280px !important;
        transition: margin-left 0.3s ease !important;
        background: white !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 0.75rem 1rem !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 999 !important;
    }
    
    .header.sidebar-collapsed {
        margin-left: 0 !important;
    }
    
    .sidebar-toggle {
        background: none !important;
        border: none !important;
        font-size: 1.25rem !important;
        color: #64748b !important;
        padding: 0.5rem !important;
        border-radius: 6px !important;
        transition: all 0.2s ease !important;
    }
    
    .sidebar-toggle:hover {
        background: #f1f5f9 !important;
        color: #334155 !important;
    }
    </style>';
    
    // Add JavaScript to ensure the sidebar functionality works
    echo '<script type="text/javascript">
    console.log("🚀 HRMS UI Fix JavaScript loaded");
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("📋 DOM Content Loaded - Starting HRMS UI fixes");
        
        // Force sidebar visibility
        const sidebar = document.querySelector(".sidebar") || document.getElementById("sidebar");
        if (sidebar) {
            console.log("✅ Sidebar element found:", sidebar);
            
            // Force styles
            sidebar.style.cssText = `
                display: block !important;
                visibility: visible !important;
                background: white !important;
                border-right: 1px solid #e2e8f0 !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
                min-height: 100vh !important;
                width: 280px !important;
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                z-index: 1000 !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                transition: transform 0.3s ease !important;
                padding: 0 !important;
                margin: 0 !important;
            `;
            
            console.log("🎨 Forced sidebar styles applied");
        } else {
            console.error("❌ Sidebar element not found!");
        }
        
        // Force nav-link styles
        const navLinks = document.querySelectorAll(".nav-link");
        console.log(`📝 Found ${navLinks.length} navigation links`);
        
        navLinks.forEach((link, index) => {
            link.style.cssText = `
                color: #64748b !important;
                text-decoration: none !important;
                display: flex !important;
                align-items: center !important;
                padding: 0.75rem 1rem !important;
                font-weight: 500 !important;
                font-size: 0.875rem !important;
                transition: all 0.2s ease !important;
                border-left: 3px solid transparent !important;
                margin: 2px 8px !important;
                border-radius: 6px !important;
                background: transparent !important;
                border-top: none !important;
                border-right: none !important;
                border-bottom: none !important;
                line-height: 1.5 !important;
                min-height: 40px !important;
            `;
            console.log(`🔗 Styled nav link ${index + 1}: "${link.textContent.trim()}"`);
        });
        
        // Force Bootstrap Icons
        const icons = document.querySelectorAll(\'[class*="bi-"], [class^="bi-"], [class*="fas"], [class^="fas"]\');
        console.log(`🎯 Found ${icons.length} icons`);
        
        icons.forEach((icon, index) => {
            if (icon.classList.contains("bi-") || icon.className.includes("bi-")) {
                icon.style.fontFamily = "bootstrap-icons, Bootstrap Icons !important";
            }
            icon.style.cssText += `
                width: 18px !important;
                margin-right: 0.75rem !important;
                font-size: 1rem !important;
                text-align: center !important;
                display: inline-block !important;
                font-style: normal !important;
            `;
            console.log(`🎨 Styled icon ${index + 1}: ${icon.className}`);
        });
        
        // Setup sidebar toggle functionality
        const sidebarToggle = document.querySelector(".sidebar-toggle") || document.getElementById("sidebarToggle");
        if (sidebarToggle && sidebar) {
            console.log("🔘 Setting up sidebar toggle functionality");
            
            sidebarToggle.addEventListener("click", function(e) {
                e.preventDefault();
                console.log("🖱️ Sidebar toggle clicked");
                
                sidebar.classList.toggle("collapsed");
                const mainContent = document.querySelector(".main-content");
                const header = document.querySelector(".header");
                
                if (mainContent) {
                    mainContent.classList.toggle("sidebar-collapsed");
                }
                if (header) {
                    header.classList.toggle("sidebar-collapsed");
                }
                
                // Store state
                const isCollapsed = sidebar.classList.contains("collapsed");
                localStorage.setItem("sidebarCollapsed", isCollapsed);
                console.log("💾 Sidebar collapsed:", isCollapsed);
            });
        } else {
            console.warn("⚠️ Sidebar toggle button not found");
        }
        
        // Restore saved state
        const savedState = localStorage.getItem("sidebarCollapsed");
        if (savedState === "true" && sidebar) {
            console.log("🔄 Restoring collapsed state");
            sidebar.classList.add("collapsed");
            const mainContent = document.querySelector(".main-content");
            const header = document.querySelector(".header");
            if (mainContent) {
                mainContent.classList.add("sidebar-collapsed");
            }
            if (header) {
                header.classList.add("sidebar-collapsed");
            }
        }
        
        // Force dropdown functionality
        const dropdownToggles = document.querySelectorAll(".dropdown-toggle");
        console.log(`📂 Setting up ${dropdownToggles.length} dropdown toggles`);
        
        dropdownToggles.forEach(function(toggle, index) {
            toggle.addEventListener("click", function(e) {
                e.preventDefault();
                console.log(`📂 Dropdown ${index + 1} clicked`);
                
                const target = this.getAttribute("data-bs-target") || this.getAttribute("href");
                if (target) {
                    const targetElement = document.querySelector(target);
                    if (targetElement) {
                        const isExpanded = this.getAttribute("aria-expanded") === "true";
                        this.setAttribute("aria-expanded", !isExpanded);
                        
                        if (isExpanded) {
                            targetElement.classList.remove("show");
                            console.log(`📂 Collapsed dropdown: ${target}`);
                        } else {
                            targetElement.classList.add("show");
                            console.log(`📂 Expanded dropdown: ${target}`);
                        }
                    } else {
                        console.warn(`⚠️ Dropdown target not found: ${target}`);
                    }
                } else {
                    console.warn("⚠️ No dropdown target specified");
                }
            });
        });
        
        console.log("✅ HRMS UI Fix initialization complete");
        
        // Log final state
        setTimeout(() => {
            console.log("🔍 Final UI State Check:");
            console.log("  - Sidebar visible:", sidebar && sidebar.offsetWidth > 0);
            console.log("  - Nav links count:", document.querySelectorAll(".nav-link").length);
            console.log("  - Icons count:", document.querySelectorAll(\'[class*="bi-"], [class*="fas"]\').length);
            console.log("  - Dropdowns count:", document.querySelectorAll(".dropdown-toggle").length);
        }, 500);
    });
    </script>';
}
?>
