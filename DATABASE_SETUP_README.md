# 🗄️ Database Setup Guide for Billbook Application

## 📋 Overview

This guide will help you set up the complete database structure for the Billbook application, ensuring that all modules (including Categories Management) work correctly.

## 🚀 Quick Setup (Recommended)

### Step 1: Run the Automated Setup
1. **Access the setup script in your browser:**
   ```
   http://your-domain/setup_database.php
   ```

2. **Follow the on-screen instructions** - the script will:
   - Create the `billing` database
   - Create all necessary tables with proper structure
   - Insert sample data for testing
   - Verify everything works correctly

### Step 2: Test the Setup
1. **Run the test script:**
   ```
   http://your-domain/test_categories.php
   ```

2. **Access the Categories Management page:**
   ```
   http://your-domain/manage_categories.php
   ```

## 🔧 Manual Setup (Alternative)

If you prefer to set up the database manually:

### Step 1: Import the SQL File
1. **Open your MySQL/phpMyAdmin**
2. **Import the file:** `database_setup.sql`
3. **Or run via command line:**
   ```bash
   mysql -u root -p < database_setup.sql
   ```

### Step 2: Verify Database Configuration
1. **Check your `db.php` file:**
   ```php
   $host = "localhost";
   $user = "root";
   $password = "";
   $dbname = "billing";
   ```

2. **Make sure these credentials match your MySQL setup**

## 📊 Database Structure

The setup creates the following tables:

### Core Tables
- **`users`** - Admin authentication
- **`categories`** - Product categories (with colors, icons, descriptions)
- **`items`** - Products/inventory
- **`employees`** - Employee management
- **`attendance`** - Attendance tracking
- **`invoices`** - Invoice management
- **`invoice_items`** - Invoice line items
- **`expenses`** - Expense tracking
- **`stock_logs`** - Stock movement history
- **`payroll`** - Payroll management
- **`settings`** - Application settings

### Advanced Features
- **Views** for better data access
- **Triggers** for automatic calculations
- **Foreign keys** for data integrity
- **Indexes** for better performance

## 🎯 Categories Table Structure

The categories table includes these fields:

```sql
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50) DEFAULT 'bi-tag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Default Categories Included:
- **Electronics** (Blue, Laptop icon)
- **Clothing** (Green, Bag icon)
- **Books** (Yellow, Book icon)
- **Food & Beverages** (Orange, Cup icon)
- **Home & Garden** (Teal, House icon)
- **Sports** (Purple, Trophy icon)
- **Toys & Games** (Pink, Controller icon)
- **Health & Beauty** (Cyan, Heart icon)

## 🔑 Default Login Credentials

After setup, use these credentials to access the application:

- **Username:** `admin`
- **Password:** `admin123`

## ✅ Verification Checklist

After running the setup, verify these items:

### Database Level:
- [ ] Database `billing` exists
- [ ] All 11 core tables created
- [ ] Sample data inserted
- [ ] Views and triggers created

### Categories Functionality:
- [ ] Can view categories list
- [ ] Can add new categories
- [ ] Can edit existing categories
- [ ] Can delete unused categories
- [ ] Color and icon customization works
- [ ] Item count tracking works

### Application Level:
- [ ] Login page works
- [ ] Categories page loads without errors
- [ ] AJAX operations work smoothly
- [ ] DataTables pagination/search works
- [ ] Mobile responsive design works

## 🛠️ Troubleshooting

### Common Issues:

#### 1. "Connection failed" Error
- **Solution:** Check MySQL is running and credentials in `db.php` are correct

#### 2. "Table doesn't exist" Error
- **Solution:** Run `setup_database.php` or import `database_setup.sql`

#### 3. Categories not saving
- **Solution:** Check database permissions and run `test_categories.php`

#### 4. AJAX operations not working
- **Solution:** Check browser console for JavaScript errors and verify database connection

### Debug Steps:
1. **Check PHP errors:** Enable `display_errors` in PHP
2. **Check database:** Run `test_categories.php`
3. **Check browser console:** Look for JavaScript errors
4. **Check network tab:** Verify AJAX requests are being sent

## 📁 File Structure

After setup, your project should have:

```
/your-project/
├── db.php                     # Database connection
├── database_setup.sql         # Complete database structure
├── setup_database.php         # Automated setup script
├── test_categories.php        # Functionality test script
├── manage_categories.php      # Categories management page
├── layouts/
│   ├── header.php            # Page header
│   ├── sidebar.php           # Navigation sidebar
│   └── footer.php            # Page footer
└── uploads/                  # File uploads directory
```

## 🔄 Schema Compatibility

The setup includes fallback mechanisms for different database schemas:

- **Modern Schema:** Full featured with all columns
- **Basic Schema:** Core functionality only
- **Legacy Schema:** Minimal structure for older databases

This ensures the application works regardless of your existing database structure.

## 🚀 Next Steps

After successful setup:

1. **Login to the application** using admin/admin123
2. **Test the Categories page** - add, edit, delete categories
3. **Customize categories** with your own colors and icons
4. **Add your products** and assign them to categories
5. **Explore other modules** like Employees, Invoices, Expenses

## 📞 Support

If you encounter any issues:

1. **Run the test scripts** to identify the problem
2. **Check the troubleshooting section** above
3. **Verify your database credentials** and permissions
4. **Ensure all files are uploaded** correctly

The database setup is designed to be robust and handle various configurations automatically. Most issues can be resolved by running the automated setup script.

---

**🎉 Congratulations!** Your Billbook application database is now ready with full Categories management functionality!