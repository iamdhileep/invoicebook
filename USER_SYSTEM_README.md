# BillBook User Management System

## Overview

The BillBook application now includes a comprehensive user management system with secure authentication, role-based access control, and user profile management.

## Features

### 🔐 Authentication
- **Secure Login**: Password hashing with bcrypt
- **Session Management**: Secure session handling
- **Password Strength**: Real-time password strength indicator
- **Remember Me**: Session persistence
- **Logout**: Secure session cleanup

### 👥 User Management
- **User Registration**: Self-registration with validation
- **Admin Management**: Admin panel for user management
- **Role-based Access**: User and Admin roles
- **Profile Management**: User profile editing
- **Password Management**: Secure password changes

### 🛡️ Security Features
- **SQL Injection Protection**: Prepared statements
- **XSS Protection**: Input sanitization
- **CSRF Protection**: Form token validation
- **Password Hashing**: bcrypt encryption
- **Session Security**: Secure session handling

## File Structure

```
billbook/
├── login.php              # Login page
├── register.php           # User registration
├── logout.php             # Secure logout
├── profile.php            # User profile management
├── manage_users.php       # Admin user management
├── auth_helper.php        # Authentication helper functions
├── setup_user_system.php  # Database setup script
├── users.sql             # Database schema
└── USER_SYSTEM_README.md # This documentation
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    profile_image VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL
);
```

### Activity Log Table
```sql
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Setup Instructions

### 1. Database Setup
Run the setup script to initialize the user system:
```
http://your-domain/setup_user_system.php
```

### 2. Default Credentials
- **Username**: admin
- **Password**: admin123

### 3. Security Recommendations
- Change default admin password immediately
- Use strong passwords
- Enable HTTPS in production
- Regular security updates

## Usage Guide

### For Users

#### Login
1. Navigate to `login.php`
2. Enter username and password
3. Click "Sign In"

#### Registration
1. Navigate to `register.php`
2. Fill in all required fields
3. Click "Create Account"

#### Profile Management
1. Login to the system
2. Navigate to `profile.php`
3. Edit your information
4. Change password if needed

### For Administrators

#### User Management
1. Login as admin
2. Navigate to `manage_users.php`
3. Add, edit, or delete users
4. Manage user roles

#### Admin Functions
- View all users
- Add new users
- Edit user information
- Change user roles
- Delete users
- Monitor user activity

## API Functions

### Authentication Helper Functions

```php
// Include the helper file
include 'auth_helper.php';

// Check if user is logged in
if (isLoggedIn()) {
    // User is logged in
}

// Check if user is admin
if (isAdmin()) {
    // User is admin
}

// Require login for a page
requireLogin();

// Require admin access
requireAdmin();

// Get current user info
$user_id = getCurrentUserId();
$username = getCurrentUsername();
$role = getCurrentUserRole();
```

### User Management Functions

```php
// Create new user
createUser($username, $email, $password, $full_name, $role);

// Update user
updateUser($user_id, $data);

// Delete user
deleteUser($user_id);

// Verify credentials
$user = verifyCredentials($username, $password);
```

## Security Features

### Password Security
- Passwords are hashed using bcrypt
- Minimum 6 characters required
- Password strength indicator
- Secure password reset functionality

### Session Security
- Secure session handling
- Session timeout
- CSRF protection
- XSS prevention

### Database Security
- Prepared statements
- Input validation
- SQL injection protection
- Data sanitization

## Customization

### Adding New Roles
1. Update the `role` ENUM in the database
2. Modify role checking functions
3. Update UI components

### Custom User Fields
1. Add columns to the users table
2. Update registration and profile forms
3. Modify helper functions

### Styling
- Bootstrap 5 framework
- Custom CSS for modern UI
- Responsive design
- Font Awesome icons

## Troubleshooting

### Common Issues

#### Login Not Working
- Check database connection
- Verify user exists in database
- Check password hashing

#### Registration Issues
- Ensure all required fields are filled
- Check email format
- Verify username uniqueness

#### Permission Errors
- Check user role
- Verify session data
- Ensure proper authentication

### Debug Mode
Enable debug mode by adding to your PHP files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Verify database setup
4. Test with default credentials

## Changelog

### Version 1.0
- Initial user management system
- Secure authentication
- Role-based access control
- User profile management
- Admin user management
- Activity logging

## License

This user management system is part of the BillBook application. 