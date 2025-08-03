# TrashSmart - Waste Collection Management System

A comprehensive waste collection management system built with PHP and MySQL, featuring separate interfaces for citizens and administrators.

## Features

### For Citizens
- **User Registration & Login**: Secure account creation and authentication
- **Request Submission**: Submit waste collection requests with details
- **Request Management**: View, update, and delete pending requests
- **Request Tracking**: Monitor request status (pending, accepted, rejected, collected)
- **Profile Management**: Manage personal information and recent requests

### For Administrators
- **Dashboard Overview**: Real-time statistics and request management
- **Request Processing**: Accept, reject, or mark requests as collected
- **User Management**: Manage citizen accounts and information
- **Status Filtering**: Filter requests by status (pending, accepted, rejected, collected)
- **Delete Management**: Remove rejected requests from the system

## Technology Stack

- **Backend**: PHP 8.x
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Tailwind CSS
- **Icons**: Font Awesome
- **Server**: Apache (XAMPP)

## Project Structure

```
TrashSmart/
├── backend/
│   ├── config/
│   │   └── database.php          # Database configuration
│   ├── includes/
│   │   └── session_helper.php    # Session management utilities
│   ├── login.php                 # User authentication
│   ├── logout.php                # Session termination
│   └── register.php              # User registration
└── frontend/
    └── TrashSmart-Project/
        ├── css/
        │   └── style.css          # Custom styles
        ├── js/
        │   ├── main.js            # Main JavaScript functions
        │   ├── admin.js           # Admin-specific functions
        │   ├── citizen-profile.js # Citizen profile functions
        │   └── admin-management.js # Admin management functions
        ├── images/                # Project images and assets
        ├── index.php              # Landing page
        ├── citizen-profile.php    # Citizen dashboard
        ├── admin-dashboard.php    # Admin dashboard
        ├── admin-management.php   # User management
        ├── company-settings.php   # Company settings
        └── create-request.php     # Request creation form
```

## Installation

1. **Prerequisites**
   - XAMPP (Apache + MySQL + PHP)
   - Web browser

2. **Setup Database**
   - Start XAMPP and ensure Apache and MySQL services are running
   - Create a database named `trashsmart`
   - Import the database schema (create necessary tables)

3. **Configure Database Connection**
   - Update `backend/config/database.php` with your database credentials
   - Default configuration uses:
     - Host: localhost
     - Username: root
     - Password: (empty)
     - Database: trashsmart

4. **Deploy Files**
   - Place the project folder in your XAMPP `htdocs` directory
   - Access the application via `http://localhost/TrashSmart/frontend/TrashSmart-Project/`

## Database Schema

### Users Table
- user_id (Primary Key)
- first_name, last_name
- email, phone
- user_type (citizen/admin)
- password (hashed)
- created_at, updated_at

### Pending Requests Table
- request_id (Primary Key)
- user_id (Foreign Key)
- waste_type, pickup_address
- preferred_pickup_date, pickup_time
- weight_category, special_instructions
- status (pending/accepted/rejected/collected)
- admin_notes
- created_at, updated_at

## Usage

### For Citizens
1. Register a new account or login with existing credentials
2. Submit waste collection requests with required details
3. Track request status and manage pending requests
4. Update or delete requests while they're still pending
5. Delete rejected requests if needed

### For Administrators
1. Login with admin credentials
2. View dashboard with request statistics
3. Process pending requests (accept/reject)
4. Mark accepted requests as collected
5. Manage user accounts and company settings
6. Delete rejected requests from the system

## Features Implemented

- ✅ Clean, responsive UI with Tailwind CSS
- ✅ Secure user authentication and session management
- ✅ Complete CRUD operations for waste collection requests
- ✅ Role-based access control (citizen/admin)
- ✅ Request status management and tracking
- ✅ Real-time dashboard statistics
- ✅ File upload and image management
- ✅ Form validation and error handling
- ✅ Mobile-responsive design
- ✅ Delete functionality for rejected requests (both citizen and admin)

## Security Features

- Password hashing for secure authentication
- Prepared statements to prevent SQL injection
- Session-based access control
- Input validation and sanitization
- CSRF protection through form tokens

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is developed for educational and demonstration purposes.

## Contact

For questions or support, please contact the development team.

---

*TrashSmart - Making waste collection smarter and more efficient.*
