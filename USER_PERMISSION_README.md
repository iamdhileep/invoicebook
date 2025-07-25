# User Permission System - BillBook

## Overview
This advanced user permission system provides role-based access control for the BillBook application. It allows you to create users with different roles and specific permissions, ensuring secure access to various features.

## Features
- Role-based user management (Admin, Manager, User)
- Granular permission control
- Remember me functionality
- User activity logging
- Session management
- Permission-based navigation

## Installation

### 1. Database Setup
Run the SQL script to create necessary tables:
```sql
-- Execute user_permission_setup.sql in your MySQL database
mysql -u your_username -p your_database < user_permission_setup.sql
```

### 2. Include Permission Checks
Add permission checks to your pages:
```php
<?php
include 'auth_guard.php';
checkPermission(PagePermissions::ATTENDANCE);
?>
```

## Default Users
The system comes with default users (password for all: `admin123`, `user123`, etc.):

| Username | Role | Permissions |
|----------|------|-------------|
| admin | Admin | All permissions |
| manager | Manager | dashboard, employees, attendance, reports, export |
| user | User | dashboard, attendance |
| hr_user | User | dashboard, employees, attendance, reports |
| accountant | User | dashboard, invoices, items, reports, export |

## Available Permissions

| Permission | Description |
|------------|-------------|
| dashboard | Access to main dashboard |
| employees | Employee management |
| attendance | Attendance management |
| invoices | Invoice management |
| items | Item/Product management |
| reports | Reports access |
| settings | Settings and user management |
| export | Export functionality |
| bulk_actions | Bulk operations |

## Usage Examples

### Check Login Status
```php
include 'auth_guard.php';
checkLogin(); // Just check if user is logged in
```

### Check Specific Permission
```php
include 'auth_guard.php';
checkPermission(PagePermissions::SETTINGS);
```

### Check Multiple Permissions (OR logic)
```php
include 'auth_guard.php';
// User needs at least one of these permissions
checkAnyPermission(['employees', 'attendance']);
```

### Check Multiple Permissions (AND logic)
```php
include 'auth_guard.php';
// User needs all of these permissions
checkAllPermissions(['export', 'reports']);
```

### Navigation Permission Checks
```php
<?php if (canShowNavItem(PagePermissions::SETTINGS)): ?>
    <a href="settings.php">Settings</a>
<?php endif; ?>
```

### Get Current User Info
```php
$user = getCurrentUserInfo();
echo "Welcome, " . $user['username'];
```

## User Management

### Access Settings Page
Navigate to `settings.php` to manage users. You need the `settings` permission.

### Add New User
1. Go to Settings page
2. Click "Add New User"
3. Fill in user details
4. Select role and permissions
5. Submit form

### Edit User
1. Find user in the table
2. Click edit button
3. Modify details as needed
4. Save changes

### User Roles

#### Admin
- Has access to everything
- Can manage other users
- Cannot be deleted by other users

#### Manager
- Has access to most features
- Cannot access settings
- Limited admin capabilities

#### User
- Basic access only
- Permissions are customizable
- Most restricted role

## Security Features

### Password Security
- Passwords are hashed using PHP's `password_hash()`
- Secure password verification
- No plaintext password storage

### Session Management
- Secure session handling
- Session timeout
- Remember me functionality (30 days)

### Activity Logging
- All login/logout actions are logged
- IP address and user agent tracking
- Activity timestamps

### Permission Validation
- Every page can check permissions
- Automatic redirects for unauthorized access
- Error messages for permission denials

## File Structure

```
/billbook/
├── settings.php              # User management interface
├── login_new.php             # Enhanced login system
├── logout.php                # Secure logout with logging
├── auth_guard.php            # Permission checking helpers
├── models/
│   └── UserPermission.php    # User permission model
├── user_permission_setup.sql # Database setup script
└── layouts/
    └── sidebar.php           # Updated with permission checks
```

## API Reference

### UserPermission Class

#### Methods

```php
// Check if user has permission
hasPermission($user_id, $permission): bool

// Check if user is admin
isAdmin($user_id): bool

// Get user permissions
getUserPermissions($user_id): array

// Validate access
validateAccess($required_permission): bool

// Get user info
getUserInfo($user_id): array

// Create user
createUser($username, $email, $password, $role, $permissions): bool

// Update user
updateUser($user_id, $username, $email, $role, $permissions, $password): bool

// Delete user
deleteUser($user_id): bool

// Authenticate user
authenticate($username, $password): int|false
```

### Helper Functions

```php
// Check login status
checkLogin(): void

// Check specific permission
checkPermission($permission): void

// Check any permission (OR)
checkAnyPermission($permissions): void

// Check all permissions (AND)
checkAllPermissions($permissions): void

// Get current user info
getCurrentUserInfo(): array|null

// Check if admin
isCurrentUserAdmin(): bool

// Navigation permission check
canShowNavItem($permission): bool

// Display messages
displayMessages(): void
```

## Troubleshooting

### Common Issues

1. **Permission Denied Error**
   - Check if user has required permission
   - Verify user role
   - Check permission spelling

2. **Login Issues**
   - Verify username/password
   - Check database connection
   - Ensure users table exists

3. **Session Problems**
   - Check session configuration
   - Verify session storage permissions
   - Clear browser cookies

### Debug Mode
Add this to check permissions:
```php
$user = getCurrentUserInfo();
var_dump($user['permissions']);
```

## Customization

### Add New Permission
1. Add to `Permissions` class in `UserPermission.php`
2. Update `$available_permissions` in `settings.php`
3. Add permission checks to relevant pages

### Custom Roles
Modify the role enum in the database:
```sql
ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','user','custom_role');
```

## Security Best Practices

1. **Change Default Passwords**: Update all default user passwords
2. **Regular Updates**: Keep the system updated
3. **Monitor Logs**: Check user activity logs regularly
4. **Limit Permissions**: Give users only necessary permissions
5. **Strong Passwords**: Enforce strong password policies
6. **HTTPS**: Use HTTPS in production
7. **Database Security**: Secure your database connection

## Support

For issues or questions:
1. Check this documentation
2. Review the code comments
3. Test with default users
4. Check error logs

## License
This user permission system is part of the BillBook project.
